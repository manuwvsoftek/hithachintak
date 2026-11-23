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

    /**
     * Streams every row (one per person — head of family, then each
     * family member) in DB-side batches of 200 enrolments, calling $sink
     * once per row. Keeps peak PHP memory bounded to one batch's worth
     * instead of materializing the whole filtered set as one array of
     * cloned Entity objects — across a campaign this size (tens of
     * thousands of Hithachintak enrolments), that array, doubled by
     * expandWithFamilyMembers() turning every enrolment into multiple
     * rows, is exactly what exhausts the 128MB memory limit on export.
     *
     * Hand-rolled LIMIT/OFFSET rather than the framework's own Model::
     * chunk() — that method has long-standing reports of returning plain
     * stdClass instead of the model's Entity class, and of pagination
     * not interacting safely with orderBy() (risking rows silently
     * skipped or duplicated across batch boundaries). created_at alone
     * isn't a unique sort key — two enrolments can share the same
     * second — so id is added as an explicit tiebreaker to keep this
     * loop's own pagination deterministic.
     */
    private function streamReportRows($actor, callable $sink): void
    {
        $batchSize = 200;
        $offset    = 0;

        while (true) {
            $chunk = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->withDetails())
                ->orderBy('enrolments.created_at', 'DESC')
                ->orderBy('enrolments.id', 'DESC')
                ->findAll($batchSize, $offset);

            if (! $chunk) {
                break;
            }

            foreach ($this->expandWithFamilyMembers($chunk) as $row) {
                $sink($row);
            }

            if (count($chunk) < $batchSize) {
                break;
            }

            $offset += $batchSize;
        }
    }

    /**
     * Collects every streamed row into one array — only for exportPdf(),
     * since Mpdf needs the whole HTML string built up front regardless;
     * CSV and Excel write each row out as it streams, never holding the
     * full set in memory at once (see exportCsv()/exportExcel()).
     */
    private function collectReportRows($actor): array
    {
        $rows = [];
        $this->streamReportRows($actor, static function ($row) use (&$rows) {
            $rows[] = $row;
        });

        return $rows;
    }

    /**
     * One row per person instead of one row per enrolment — the head of
     * family's own row, followed by one additional row per family member
     * added at the same sitting. Every enrolment-level column (receipt_no,
     * programme, location, Karyakarta, status, dates) is shared/duplicated
     * across all of a group's rows, since they're genuinely one
     * transaction/receipt covering several people — member_name/
     * member_phone/member_type/amount vary per row. Family members have
     * no email/PAN of their own, so those columns come back blank on
     * their rows.
     *
     * Amount is split evenly across the group (for Hithachintak this
     * lands exactly on the per-head rate, since that's how the amount was
     * computed in the first place — see EnrolmentService::start()) rather
     * than repeating the full enrolment amount on every row, which would
     * make a spreadsheet SUM() of the Amount column overcount by however
     * many family members exist. Equal division can leave a rounding
     * remainder (e.g. ₹100 / 3 = ₹33.33, and 3 × ₹33.33 = ₹99.99) — the
     * head of family's row absorbs that remainder, so the group's rows
     * always add back up to exactly the original amount.
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
            $members   = $grouped[$row->id] ?? [];
            $headCount = 1 + count($members);
            $total     = (float) $row->amount;
            $share     = $headCount > 1 ? round($total / $headCount, 2) : $total;

            $row->member_type = 'Head of Family';
            $row->amount      = $headCount > 1 ? round($total - $share * ($headCount - 1), 2) : $total;
            $expanded[]        = $row;

            foreach ($members as $fm) {
                $familyRow               = clone $row;
                $familyRow->member_type  = 'Family Member';
                $familyRow->member_name  = trim((string) ($fm['name'] ?? ''));
                $familyRow->member_phone = $fm['contact_number'] ?? null;
                $familyRow->member_email = null;
                $familyRow->member_pan   = null;
                $familyRow->amount       = $share;
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

        // CAMPAIGN LITE MODE — the KPI/reconciliation blocks below (amount
        // totals, person headcount, cash-remitted reconciliation) are each
        // an extra query beyond the one the page actually needs (the
        // paginated, family-expanded list above). Hidden and left at
        // cheap defaults unless "Show counts" (?showcounts=1) is clicked
        // — nothing auto-runs these on every page load, only an explicit
        // click.
        $totalAmount   = 0.0;
        $cashCollected = 0.0;
        $totalPersons  = $pager->getTotal();
        $remitted      = 0.0;
        $showCounts    = (bool) $this->request->getGet('showcounts');

        if ($showCounts) {
            $sums = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->joinDetails())
                ->select('
                    SUM(enrolments.amount) AS total_amount,
                    SUM(CASE WHEN enrolments.payment_mode = "cash" THEN enrolments.amount ELSE 0 END) AS cash_amount
                ', false)
                ->first();
            $totalAmount   = (float) ($sums?->total_amount ?? 0);
            $cashCollected = (float) ($sums?->cash_amount ?? 0);

            $headcount = $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->joinDetails())
                ->join('enrolment_family_members efm', 'efm.enrolment_id = enrolments.id', 'left')
                ->select('COUNT(DISTINCT enrolments.id) AS enrolment_count, COUNT(efm.id) AS family_count', false)
                ->first();
            $totalPersons = (int) ($headcount->enrolment_count ?? 0) + (int) ($headcount->family_count ?? 0);

            $userIds = array_column(
                $this->applyFilters((new EnrolmentModel())->scopedTo($actor)->joinDetails())
                    ->select('enrolments.collected_by_user_id')->distinct()->findAll(),
                'collected_by_user_id'
            );
            $remitted = $userIds === [] ? 0.0 : (float) ((new CashRemittanceModel())
                ->whereIn('user_id', $userIds)->where('status', 'remitted')
                ->selectSum('amount')->first()['amount'] ?? 0);
        }

        $get = $this->request->getGet();

        return view('reports/index', [
            'title'         => 'Reports',
            'rows'          => $rows,
            'pager'         => $pager,
            'totalCount'    => $pager->getTotal(),
            'totalPersons'  => $totalPersons,
            'showCounts'    => $showCounts,
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

        $out = fopen('php://temp', 'w+');
        fputcsv($out, array_map(fn ($f) => self::FIELD_CATALOG[$f], $fields));
        $this->streamReportRows($this->currentUser(), static function ($r) use ($out, $fields) {
            fputcsv($out, array_map(static fn ($f) => report_field_value($r, $f), $fields));
        });
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
        // PhpSpreadsheet's own per-cell overhead scales with row count
        // regardless of how rows are fetched — streaming the DB side
        // (below) avoids ALSO holding a full duplicate PHP array of rows
        // in memory, but this export can still legitimately need more
        // than the default 128M once a filtered set runs into the tens
        // of thousands of people. Scoped to this request only.
        ini_set('memory_limit', '512M');

        $fields = $this->selectedFields();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $headers = array_map(fn ($f) => self::FIELD_CATALOG[$f], $fields);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

        $rowNum = 2;
        $this->streamReportRows($this->currentUser(), function ($r) use ($sheet, $fields, &$rowNum) {
            $sheet->fromArray(array_map(static fn ($f) => report_field_value($r, $f), $fields), null, 'A' . $rowNum);
            $rowNum++;
        });
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
        // Mpdf needs the whole HTML string up front, so unlike the CSV/
        // Excel exports this one can't avoid holding every row (and the
        // HTML built from them) in memory at once — streaming the DB
        // fetch still avoids doubling that via a second buffered result
        // set, but very large filtered sets are still heavier here than
        // as CSV/Excel. Prefer those two for a full, unfiltered export.
        ini_set('memory_limit', '512M');

        $fields = $this->selectedFields();
        $rows   = $this->collectReportRows($this->currentUser());

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
