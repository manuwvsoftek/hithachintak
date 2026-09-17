<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\User;
use App\Libraries\Msg91\Msg91Sms;
use App\Libraries\Msg91\Msg91WhatsApp;
use App\Libraries\Notify\EmailSender;
use App\Models\AuditLogModel;
use App\Models\EmailSettingModel;
use App\Models\GatewayCredentialModel;
use App\Models\PrantModel;
use App\Models\RolePermissionModel;
use App\Models\SmsSettingModel;
use App\Models\SmsTemplateModel;
use App\Models\WhatsappSettingModel;
use App\Models\WhatsappTemplateModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SettingsController extends BaseController
{
    private const TABS = [
        'trust'    => 'Trust Details',
        'payment'  => 'Payment Gateways',
        'sms'      => 'SMS (MSG91)',
        'whatsapp' => 'WhatsApp (MSG91)',
        'email'    => 'Email',
        'roles'    => 'Roles & Permissions',
    ];

    // ---------------------------------------------------------------
    // Trust Details — each Prant's registered Trust name, address, PIN
    // and PAN, shown on the public Contact Us page. Edited one Prant at
    // a time or in bulk via spreadsheet, same pattern as the Masters
    // locations import.
    // ---------------------------------------------------------------

    public function trust()
    {
        $editPrantId = (int) ($this->request->getGet('edit') ?: 0);

        return view('settings/trust', [
            'title'     => 'Settings & Integrations',
            'tabs'      => self::TABS,
            'activeTab' => 'trust',
            'prants'    => (new PrantModel())->orderBy('name', 'ASC')->findAll(),
            'editPrant' => $editPrantId ? (new PrantModel())->find($editPrantId) : null,
        ]);
    }

    public function saveTrust($prantId)
    {
        $prantId = (int) $prantId;

        $model = new PrantModel();
        if (! $model->find($prantId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $updated = $model->update($prantId, [
            'trust_name'    => trim((string) $this->request->getPost('trust_name')) ?: null,
            'trust_address' => trim((string) $this->request->getPost('trust_address')) ?: null,
            'trust_pincode' => trim((string) $this->request->getPost('trust_pincode')) ?: null,
            'trust_pan'     => strtoupper(trim((string) $this->request->getPost('trust_pan'))) ?: null,
            'trust_phone'   => trim((string) $this->request->getPost('trust_phone')) ?: null,
            'trust_email'   => trim((string) $this->request->getPost('trust_email')) ?: null,
        ]);

        if (! $updated) {
            return redirect()->to('/admin/settings/trust?edit=' . $prantId)->with('errors', $model->errors());
        }

        (new AuditLogModel())->record($this->currentUser()->id, 'prant_trust_info_saved', 'prant', $prantId);

        return redirect()->to('/admin/settings/trust')->with('success', 'Trust details saved.');
    }

    /** Downloadable .xlsx sample for the Trust Details bulk import — pre-filled with every existing Prant name so the admin only has to fill in the rest. */
    public function downloadTrustTemplate()
    {
        $prants = (new PrantModel())->orderBy('name', 'ASC')->findAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Trust Details');
        $sheet->fromArray(['Prant', 'Registered Trust Name', 'Address', 'PIN Code', 'PAN', 'Phone', 'Email'], null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $rows = array_map(static fn ($p) => [
            $p['name'], $p['trust_name'] ?? '', $p['trust_address'] ?? '', $p['trust_pincode'] ?? '', $p['trust_pan'] ?? '', $p['trust_phone'] ?? '', $p['trust_email'] ?? '',
        ], $prants);
        $sheet->fromArray($rows, null, 'A2');
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(28);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['How to fill this template'],
            ['One row per Prant — the Prant column is pre-filled with every existing Prant; do not rename it.'],
            ['A row whose Prant name does not exactly match an existing Prant is skipped, not created.'],
            ['Leave a cell blank to clear that field, or leave the whole row unchanged to leave it as-is.'],
            ['PAN must be in the standard format, e.g. AAATV1234C. PIN Code must be 6 digits. Phone must be 10 digits.'],
        ], null, 'A1');
        $notes->getColumnDimension('A')->setWidth(95);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $body = ob_get_clean();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="trust-details-template.xlsx"')
            ->setBody($body);
    }

    /**
     * Imports Trust Details from an uploaded spreadsheet (the format
     * downloadTrustTemplate() produces). Matches rows to existing Prants
     * by exact name — never creates a Prant, since Trust info without a
     * location entry makes no sense here.
     */
    public function bulkImportTrust()
    {
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->to('/admin/settings/trust')->with('error', 'Please choose a file to import.');
        }
        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->to('/admin/settings/trust')->with('error', 'Unsupported file type — upload the .xlsx template.');
        }

        try {
            $rows = IOFactory::load($file->getTempName())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return redirect()->to('/admin/settings/trust')->with('error', "Couldn't read that file — is it a valid Excel/CSV file?");
        }

        array_shift($rows); // header row

        $model   = new PrantModel();
        $byName  = [];
        $current = [];
        foreach ($model->findAll() as $p) {
            $byName[mb_strtolower(trim($p['name']))] = $p['id'];
            $current[$p['id']] = $p;
        }

        $updated = $skippedNoMatch = $skippedInvalid = $skippedUnchanged = 0;
        $badRows = [];

        foreach ($rows as $i => $row) {
            $prantName = trim((string) ($row[0] ?? ''));
            if ($prantName === '') {
                continue; // blank row
            }

            $prantId = $byName[mb_strtolower($prantName)] ?? null;
            if ($prantId === null) {
                $skippedNoMatch++;
                continue;
            }

            $pincode = trim((string) ($row[3] ?? ''));
            $pan     = strtoupper(trim((string) ($row[4] ?? '')));
            $phone   = trim((string) ($row[5] ?? ''));
            $email   = trim((string) ($row[6] ?? ''));

            if ($pincode !== '' && ! preg_match('/^[0-9]{6}$/', $pincode)) {
                $skippedInvalid++;
                $badRows[] = $prantName . ' (PIN Code)';
                continue;
            }
            if ($pan !== '' && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
                $skippedInvalid++;
                $badRows[] = $prantName . ' (PAN)';
                continue;
            }
            if ($phone !== '' && ! preg_match('/^[0-9]{10}$/', $phone)) {
                $skippedInvalid++;
                $badRows[] = $prantName . ' (Phone)';
                continue;
            }
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedInvalid++;
                $badRows[] = $prantName . ' (Email)';
                continue;
            }

            $incoming = [
                'trust_name'    => trim((string) ($row[1] ?? '')) ?: null,
                'trust_address' => trim((string) ($row[2] ?? '')) ?: null,
                'trust_pincode' => $pincode ?: null,
                'trust_pan'     => $pan ?: null,
                'trust_phone'   => $phone ?: null,
                'trust_email'   => $email ?: null,
            ];

            // Untouched rows for a Prant that has never been configured
            // round-trip as all-blank (the template pre-fills every Prant,
            // configured or not) — skip those rather than counting them as
            // an "update" and writing a no-op row.
            $existing = $current[$prantId];
            $changed  = false;
            foreach ($incoming as $field => $value) {
                if (($existing[$field] ?? null) !== $value) {
                    $changed = true;
                    break;
                }
            }
            if (! $changed) {
                $skippedUnchanged++;
                continue;
            }

            $model->update($prantId, $incoming);
            $updated++;
        }

        (new AuditLogModel())->record($this->currentUser()->id, 'trust_details_bulk_imported', null, null, [
            'updated' => $updated,
        ]);

        $summary = $updated > 0
            ? "Updated Trust details for {$updated} Prant(s)."
            : 'No changes to apply — every row already matched what was already saved.';
        if ($skippedNoMatch > 0) {
            $summary .= " {$skippedNoMatch} row(s) didn't match an existing Prant name and were skipped.";
        }
        if ($skippedInvalid > 0) {
            $summary .= ' ' . $skippedInvalid . ' row(s) had an invalid PIN Code or PAN and were skipped (' . implode(', ', array_slice($badRows, 0, 5)) . (count($badRows) > 5 ? ', …' : '') . ').';
        }

        $hasProblems = $skippedNoMatch > 0 || $skippedInvalid > 0;

        return redirect()->to('/admin/settings/trust')->with($updated > 0 || ! $hasProblems ? 'success' : 'error', $summary);
    }

    // ---------------------------------------------------------------
    // Payment Gateways (Cashfree — one merchant account per Prant)
    // ---------------------------------------------------------------

    public function payment()
    {
        $prants = (new PrantModel())->orderBy('name', 'ASC')->findAll();
        $gcModel = new GatewayCredentialModel();

        $gateways = array_map(static function ($p) use ($gcModel) {
            $row = $gcModel->forPrant($p['id']);

            return [
                'prant'    => $p,
                'status'   => $row['status'] ?? 'pending',
                'merchant' => $row['merchant_id'] ?? null,
            ];
        }, $prants);

        $editPrantId = (int) ($this->request->getGet('edit') ?: 0);
        $editRow     = $editPrantId ? $gcModel->forPrant($editPrantId) : null;
        $editPrant   = $editPrantId ? (new PrantModel())->find($editPrantId) : null;

        return view('settings/payment', [
            'title'       => 'Settings & Integrations',
            'tabs'        => self::TABS,
            'activeTab'   => 'payment',
            'gateways'    => $gateways,
            'editPrant'   => $editPrant,
            'editRow'     => $editRow,
        ]);
    }

    public function savePayment($prantId)
    {
        $prantId = (int) $prantId;

        (new GatewayCredentialModel())->saveForPrant($prantId, [
            'app_id'      => $this->request->getPost('app_id'),
            'secret_key'  => $this->request->getPost('secret_key'),
            'merchant_id' => $this->request->getPost('merchant_id'),
            'environment' => $this->request->getPost('environment') ?: 'sandbox',
        ]);

        (new AuditLogModel())->record($this->currentUser()->id, 'gateway_configured', 'prant', $prantId);

        return redirect()->to('/admin/settings/payment')->with('success', 'Gateway saved and verified.');
    }

    // ---------------------------------------------------------------
    // SMS (MSG91)
    // ---------------------------------------------------------------

    public function sms()
    {
        return view('settings/sms', [
            'title' => 'Settings & Integrations', 'tabs' => self::TABS, 'activeTab' => 'sms',
            'settings'  => (new SmsSettingModel())->current(),
            'templates' => (new SmsTemplateModel())->findAll(),
        ]);
    }

    public function saveSms()
    {
        (new SmsSettingModel())->saveSettings([
            'auth_key'      => $this->request->getPost('auth_key'),
            'sender_id'     => $this->request->getPost('sender_id'),
            'dlt_entity_id' => $this->request->getPost('dlt_entity_id'),
            'route'         => $this->request->getPost('route'),
        ]);
        (new AuditLogModel())->record($this->currentUser()->id, 'sms_settings_saved');

        return redirect()->to('/admin/settings/sms')->with('success', 'SMS settings saved.');
    }

    public function testSms()
    {
        $phone = preg_replace('/\D/', '', (string) $this->request->getPost('phone'));
        $ok    = (new Msg91Sms())->sendOtp($phone, '123456');

        return redirect()->to('/admin/settings/sms')
            ->with($ok ? 'success' : 'error', $ok ? 'Test SMS dispatched.' : 'Test SMS failed — check the logs.');
    }

    public function updateSmsTemplate($id)
    {
        $id = (int) $id;

        $model = new SmsTemplateModel();
        if (! $model->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->update($id, [
            'dlt_template_id'   => $this->request->getPost('dlt_template_id') ?: null,
            'msg91_template_id' => $this->request->getPost('msg91_template_id') ?: null,
            'status'            => $this->request->getPost('status') ?: 'pending',
        ]);

        return redirect()->to('/admin/settings/sms')->with('success', 'Template updated.');
    }

    // ---------------------------------------------------------------
    // WhatsApp (MSG91)
    // ---------------------------------------------------------------

    public function whatsapp()
    {
        return view('settings/whatsapp', [
            'title' => 'Settings & Integrations', 'tabs' => self::TABS, 'activeTab' => 'whatsapp',
            'settings'  => (new WhatsappSettingModel())->current(),
            'templates' => (new WhatsappTemplateModel())->findAll(),
        ]);
    }

    public function saveWhatsapp()
    {
        (new WhatsappSettingModel())->saveSettings([
            'integrated_number' => $this->request->getPost('integrated_number'),
            'api_key'           => $this->request->getPost('api_key'),
            'waba_namespace_id' => $this->request->getPost('waba_namespace_id'),
        ]);
        (new AuditLogModel())->record($this->currentUser()->id, 'whatsapp_settings_saved');

        return redirect()->to('/admin/settings/whatsapp')->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsapp()
    {
        $phone = preg_replace('/\D/', '', (string) $this->request->getPost('phone'));
        $ok    = (new Msg91WhatsApp())->sendTemplate('otp_alert', $phone, ['123456']);

        return redirect()->to('/admin/settings/whatsapp')
            ->with($ok ? 'success' : 'error', $ok ? 'Test message dispatched.' : 'Test message failed — check the logs.');
    }

    public function updateWhatsappTemplate($id)
    {
        $id = (int) $id;

        $model = new WhatsappTemplateModel();
        if (! $model->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model->update($id, ['status' => $this->request->getPost('status') ?: 'pending']);

        return redirect()->to('/admin/settings/whatsapp')->with('success', 'Template updated.');
    }

    // ---------------------------------------------------------------
    // Email
    // ---------------------------------------------------------------

    public function email()
    {
        return view('settings/email', [
            'title' => 'Settings & Integrations', 'tabs' => self::TABS, 'activeTab' => 'email',
            'settings' => (new EmailSettingModel())->current(),
        ]);
    }

    public function saveEmail()
    {
        (new EmailSettingModel())->saveSettings([
            'provider'     => $this->request->getPost('provider'),
            'from_address' => $this->request->getPost('from_address'),
            'from_name'    => $this->request->getPost('from_name'),
            'smtp_host'    => $this->request->getPost('smtp_host'),
            'smtp_port'    => $this->request->getPost('smtp_port'),
            'smtp_user'    => $this->request->getPost('smtp_user'),
            'smtp_pass'    => $this->request->getPost('smtp_pass'),
            'smtp_crypto'  => $this->request->getPost('smtp_crypto'),
            'api_key'      => $this->request->getPost('api_key'),
            'daily_quota'  => $this->request->getPost('daily_quota'),
        ]);
        (new AuditLogModel())->record($this->currentUser()->id, 'email_settings_saved');

        return redirect()->to('/admin/settings/email')->with('success', 'Email settings saved.');
    }

    public function testEmail()
    {
        $to = (string) $this->request->getPost('email');
        $ok = (new EmailSender())->send($to, 'Test', 'Hithachintak Abhiyan — test email', '<p>This is a test email from your Hithachintak Abhiyan platform.</p>');

        return redirect()->to('/admin/settings/email')
            ->with($ok ? 'success' : 'error', $ok ? 'Test email dispatched.' : 'Test email failed — check the logs.');
    }

    // ---------------------------------------------------------------
    // Roles & Permissions — lives under Settings & Integrations (Dev
    // Admin's own area) rather than Users & Hierarchy, so it's reachable
    // only by whoever already holds Full here. Moved from
    // UsersController: same table, same logic, just relocated.
    // ---------------------------------------------------------------

    public function roles()
    {
        return view('settings/roles', [
            'title'      => 'Settings & Integrations',
            'tabs'       => self::TABS,
            'activeTab'  => 'roles',
            'permMatrix' => (new RolePermissionModel())->matrix(),
            'permLevels' => RolePermissionModel::LEVELS,
            'permRoles'  => array_keys(User::ROLE_LABELS),
            'roleLabels' => User::ROLE_LABELS,
        ]);
    }

    public function updatePermission()
    {
        $role   = $this->request->getPost('role');
        $module = $this->request->getPost('module');
        $level  = $this->request->getPost('level');

        if (! in_array($level, RolePermissionModel::LEVELS, true)) {
            return redirect()->to('/admin/settings/roles')->with('error', 'Invalid permission level.');
        }

        $model    = new RolePermissionModel();
        $existing = $model->where('role', $role)->where('module', $module)->first();
        if ($existing) {
            $model->update($existing['id'], ['level' => $level]);
        } else {
            $model->insert(['role' => $role, 'module' => $module, 'level' => $level]);
        }

        (new AuditLogModel())->record($this->currentUser()->id, 'permission_changed', 'role_permissions', null, [
            'role' => $role, 'module' => $module, 'level' => $level,
        ]);

        return redirect()->to('/admin/settings/roles')->with('success', 'Permission updated.');
    }
}
