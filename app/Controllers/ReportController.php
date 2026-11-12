<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\User;
use App\Models\CashRemittanceModel;
use App\Models\EnrolmentFamilyMemberModel;
use App\Models\EnrolmentModel;
use App\Models\JilaModel;
use App\Models\PrakhandModel;
use App\Models\PrantModel;
use App\Models\ProgrammeModel;
use App\Models\ReportTemplateModel;
use App\Models\UserModel;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends BaseController
{
    /** Every field a report can include — key => column selected by EnrolmentModel::withDetails(), or synthesized by expandWithFamilyMembers(). */
    private const FIELD_CATALOG = [
        'receipt_no'      => 'Receipt No',
        'member_name'     => 'Member Name',
        'member_type'     => 'Member Type',
        'member_phone'    => 'Member Phone',
        'member_email'    => 'Member Email',
        'member_pan'      => 'Member PAN',
        'programme_name'  => 'Programme',
        'prant_name'      => 'Prant',
        'jila_name'       => 'Jila',
        'prakhand_name'   => 'Prakhand',
        'karyakarta_name' => 'Karyakarta',
        'amount'          => 'Amount',
        'payment_mode'    => 'Payment Mode',
        'status'          => 'Status',
        'language'        => 'Language',
        'created_at'      => 'Enrolled On',
        'paid_at'         => 'Paid On',
    ];

    private const DEFAULT_FIELDS = [
        'receipt_no', 'member_name', 'member_type', 'programme_name', 'prant_name', 'jila_name',
        'karyakarta_name', 'amount', 'payment_mode', 'status', 'created_at',
    ];

    private const FILTER_KEYS = ['q', 'programme', 'status', 'mode', 'from', 'to', 'prant_id', 'jila_id', 'prakhand_id', 'karyakarta_id'];

    /** A filter GET param applied identically to the list, its totals, and every export format, so all four always agree. */
    private function applyFilters($model)
    {
        $get = $this->request->getGet();

        if (! empty($get['programme'])) {
            $model->where('programmes.code', $get['programme']);
        }
        if (! empty($get['status'])) {
            $model->where('enrolments.status', $get['status']);
        }
        if (! empty($get['mode'])) {
            $model->where('enrolments.payment_mode', $get['mode']);
        }
        if (! empty($get['from'])) {
            $model->where('enrolments.created_at >=', $get['from'] . ' 00:00:00');
        }
        if (! empty($get['to'])) {
            $model->where('enrolments.created_at <=', $get['to'] . ' 23:59:59');
        }
        if (! empty($get['prant_id'])) {
            $model->where('enrolments.prant_id', $get['prant_id']);
        }
        if (! empty($get['jila_id'])) {
            $model->where('enrolments.jila_id', $get['jila_id']);
        }
        if (! empty($get['prakhand_id'])) {
            $model->where('enrolments.prakhand_id', $get['prakhand_id']);
        }
        if (! empty($get['karyakarta_id'])) {
            $model->where('enrolments.collected_by_user_id', $get['karyakarta_id']);
        }
        if (! empty($get['q'])) {
            $search = trim($get['q']);
            $model->groupStart()
                ->like('members.name', $search)
                ->orLike('members.phone', $search)
                ->orLike('enrolments.receipt_no', $search)
                ->orLike('users.name', $search)
                ->groupEnd();
        }

        return $model;
    }

    private function selectedFields(): array
    {
        $requested = $this->request->getGet('fields');
        if (! is_array($requested) || ! $requested) {
            return self::DEFAULT_FIELDS;
        }

        $valid = array_values(array_intersect(array_keys(self::FIELD_CATALOG), $requested));

        return $valid ?: self::DEFAULT_FIELDS;
    }

    private function reportRows($actor): array
    {
        $rows = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->withDetails())
            ->orderBy('enrolments.created_at', 'DESC')->findAll();

        return $this->expandWithFamilyMembers($rows);
    }

    /**
     * One row per person instead of one row per enrolment — the head of
     * family's own row, unchanged, followed by one additional row per
     * family member added at the same sitting. Every enrolment-level
     * column (receipt_no, programme, location, Karyakarta, amount,
     * status, dates) is shared/duplicated across all of a group's rows,
     * since they're genuinely one transaction/receipt covering several
     * people — only member_name/member_phone/member_type vary per row.
     * Family members have no email/PAN of their own, so those columns
     * come back blank on their rows.
     *
     * @param list<\App\Entities\Enrolment> $rows
     * @return list<\App\Entities\Enrolment>
     */
    private function expandWithFamilyMembers(array $rows): array
    {
        if (! $rows) {
            return $rows;
        }

        $ids     = array_map(static fn ($row) => $row->id, $rows);
        $grouped = (new EnrolmentFamilyMemberModel())->forEnrolments($ids);

        $expanded = [];
        foreach ($rows as $row) {
            $row->member_type = 'Head of Family';
            $expanded[]        = $row;

            foreach ($grouped[$row->id] ?? [] as $fm) {
                $familyRow               = clone $row;
                $familyRow->member_type  = 'Family Member';
                $familyRow->member_name  = trim((string) ($fm['name'] ?? ''));
                $familyRow->member_phone = $fm['contact_number'] ?? null;
                $familyRow->member_email = null;
                $familyRow->member_pan   = null;
                $expanded[]              = $familyRow;
            }
        }

        return $expanded;
    }

    public function index()
    {
        $actor = $this->currentUser();

        // A saved template redirects to its own filters+fields as a plain
        // query string, so the resulting URL stays a normal, bookmarkable
        // report link rather than a second code path through this action.
        $templateId = $this->request->getGet('template');
        if ($templateId) {
            $tpl = (new ReportTemplateModel())->find((int) $templateId);
            if ($tpl && (int) $tpl['user_id'] === $actor->id) {
                $query           = (array) json_decode($tpl['filters_json'], true);
                $query['fields'] = (array) json_decode($tpl['fields_json'], true);

                return redirect()->to('/admin/reports?' . http_build_query($query));
            }
        }

        $model = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->withDetails());
        $rows  = $model->orderBy('enrolments.created_at', 'DESC')->paginate(25);
        $pager = $model->pager;

        $rows = $this->expandWithFamilyMembers($rows);

        $sums = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->withDetails())
            ->select('
                SUM(enrolments.amount) AS total_amount,
                SUM(CASE WHEN enrolments.payment_mode = "cash" THEN enrolments.amount ELSE 0 END) AS cash_amount
            ', false)
            ->first();
        $totalAmount   = (float) ($sums?->total_amount ?? 0);
        $cashCollected = (float) ($sums?->cash_amount ?? 0);

        // Headcount including family members — a single enrolment row can
        // cover several people (the head of family plus each additional
        // member added at the same sitting), so the row count alone
        // undercounts how many people this filtered report actually
        // covers. A plain aggregate (no GROUP BY) always collapses to
        // exactly one row, so this is safe regardless of table size.
        $headcount = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->withDetails())
            ->join('enrolment_family_members efm', 'efm.enrolment_id = enrolments.id', 'left')
            ->select('COUNT(DISTINCT enrolments.id) AS enrolment_count, COUNT(efm.id) AS family_count', false)
            ->first();
        $totalPersons = (int) ($headcount->enrolment_count ?? 0) + (int) ($headcount->family_count ?? 0);

        // Cash actually remitted is tracked on cash_remittances, not
        // inferred from the enrolment's own status, for an accurate
        // reconciliation figure — same set of collectors as the filtered
        // enrolments, without re-fetching every one of their rows.
        $userIds = array_column(
            $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->joinDetails())
                ->select('enrolments.collected_by_user_id')->distinct()->findAll(),
            'collected_by_user_id'
        );
        $remitted = $userIds === [] ? 0.0 : (float) ((new CashRemittanceModel())
            ->whereIn('user_id', $userIds)->where('status', 'remitted')
            ->selectSum('amount')->first()['amount'] ?? 0);

        $get = $this->request->getGet();

        return view('reports/index', [
            'title'         => 'Reports',
            'rows'          => $rows,
            'pager'         => $pager,
            'totalCount'    => $pager->getTotal(),
            'totalPersons'  => $totalPersons,
            'totalAmount'   => $totalAmount,
            'programmes'    => (new ProgrammeModel())->active(),
            'prants'        => (new PrantModel())->orderBy('name', 'ASC')->findAll(),
            'jilas'         => ! empty($get['prant_id']) ? (new JilaModel())->forPrant((int) $get['prant_id']) : [],
            'prakhands'     => ! empty($get['jila_id']) ? (new PrakhandModel())->forJila((int) $get['jila_id']) : [],
            'karyakartas'   => (new UserModel())->visibleTo($actor)->where('role', User::ROLE_KARYAKARTA)->orderBy('name', 'ASC')->findAll(),
            'templates'     => (new ReportTemplateModel())->forUser($actor->id),
            'fieldCatalog'  => self::FIELD_CATALOG,
            'fields'        => $this->selectedFields(),
            'cashCollected' => $cashCollected,
            'remitted'      => $remitted,
            'outstanding'   => max(0, $cashCollected - $remitted),
        ]);
    }

    public function saveTemplate()
    {
        $actor = $this->currentUser();
        $name  = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return redirect()->back()->with('error', 'Give the report template a name.');
        }

        $post    = $this->request->getPost();
        $filters = array_filter(
            array_intersect_key($post, array_flip(self::FILTER_KEYS)),
            static fn ($v) => $v !== null && $v !== ''
        );
        $fields  = array_values(array_intersect(array_keys(self::FIELD_CATALOG), $post['fields'] ?? []));

        (new ReportTemplateModel())->insert([
            'user_id'      => $actor->id,
            'name'         => $name,
            'filters_json' => json_encode($filters),
            'fields_json'  => json_encode($fields ?: self::DEFAULT_FIELDS),
        ]);

        return redirect()->to('/admin/reports')->with('success', "Saved report template \"{$name}\".");
    }

    public function deleteTemplate($id)
    {
        $id = (int) $id;

        $actor = $this->currentUser();
        $tpl   = (new ReportTemplateModel())->find($id);
        if ($tpl && (int) $tpl['user_id'] === $actor->id) {
            (new ReportTemplateModel())->delete($id);

            return redirect()->to('/admin/reports')->with('success', 'Report template removed.');
        }

        return redirect()->to('/admin/reports')->with('error', 'Template not found.');
    }

    public function exportCsv()
    {
        $fields = $this->selectedFields();
        $rows   = $this->reportRows($this->currentUser());

        $out = fopen('php://temp', 'w+');
        fputcsv($out, array_map(fn ($f) => self::FIELD_CATALOG[$f], $fields));
        foreach ($rows as $r) {
            fputcsv($out, array_map(static fn ($f) => report_field_value($r, $f), $fields));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="report-' . date('Y-m-d') . '.csv"')
            ->setBody($csv);
    }

    public function exportExcel()
    {
        $fields = $this->selectedFields();
        $rows   = $this->reportRows($this->currentUser());

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $headers = array_map(fn ($f) => self::FIELD_CATALOG[$f], $fields);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->fromArray(array_map(static fn ($f) => report_field_value($r, $f), $fields), null, 'A' . $rowNum);
            $rowNum++;
        }
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $body = ob_get_clean();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="report-' . date('Y-m-d') . '.xlsx"')
            ->setBody($body);
    }

    public function exportPdf()
    {
        $fields = $this->selectedFields();
        $rows   = $this->reportRows($this->currentUser());

        $html = view('reports/pdf', [
            'fieldCatalog' => self::FIELD_CATALOG,
            'fields'       => $fields,
            'rows'         => $rows,
            'generatedAt'  => date('d M Y, h:i A'),
            'fieldValue'   => static fn ($row, $field) => report_field_value($row, $field),
        ]);

        $mpdf = new Mpdf([
            'mode'    => 'UTF-8',
            'format'  => 'A4-L',
            'tempDir' => WRITEPATH . 'fonts_cache',
        ]);
        $mpdf->WriteHTML($html);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="report-' . date('Y-m-d') . '.pdf"')
            ->setBody($mpdf->Output('', 'S'));
    }
}
