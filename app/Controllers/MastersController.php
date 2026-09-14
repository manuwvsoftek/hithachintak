<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Enrolment\I18n;
use App\Models\AuditLogModel;
use App\Models\JilaModel;
use App\Models\PrakhandModel;
use App\Models\PrantModel;
use App\Models\ProgrammeModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MastersController extends BaseController
{
    public function index()
    {
        $tab = $this->request->getGet('tab') ?: 'programmes';

        if ($tab === 'locations') {
            return $this->locationsTab();
        }

        return view('masters/index', [
            'title'      => 'Masters',
            'tab'        => $tab,
            'programmes' => (new ProgrammeModel())->orderBy('sort_order', 'ASC')->findAll(),
        ]);
    }

    /**
     * The three-pane Prant / Jila / Prakhand browser: only the panes that
     * can actually be shown get their child rows fetched — every Prant's
     * Jila *count* for the left pane, but the full Jila list (with each
     * one's Prakhand count) only for whichever Prant is selected, and the
     * full Prakhand list only for whichever Jila is selected within it.
     */
    private function locationsTab()
    {
        $db = db_connect();
        $prantModel = new PrantModel();

        $search = trim((string) $this->request->getGet('q'));
        $prants = $search !== ''
            ? $prantModel->like('name', $search)->orderBy('name', 'ASC')->findAll()
            : $prantModel->orderBy('name', 'ASC')->findAll();

        $jilaCounts = $prants ? array_column(
            $db->table('jilas')->select('prant_id, COUNT(*) AS cnt')
                ->whereIn('prant_id', array_column($prants, 'id'))->groupBy('prant_id')
                ->get()->getResultArray(),
            'cnt', 'prant_id'
        ) : [];
        foreach ($prants as &$p) {
            $p['jila_count'] = (int) ($jilaCounts[$p['id']] ?? 0);
        }
        unset($p);

        $selectedPrantId = (int) ($this->request->getGet('prant') ?: ($prants[0]['id'] ?? 0));
        $currentPrant     = null;
        foreach ($prants as $p) {
            if ((int) $p['id'] === $selectedPrantId) {
                $currentPrant = $p;
                break;
            }
        }
        if (! $currentPrant && $selectedPrantId) {
            $currentPrant = $prantModel->find($selectedPrantId);
        }

        $jilas = $selectedPrantId ? (new JilaModel())->forPrant($selectedPrantId) : [];
        if ($currentPrant) {
            // Always set directly from the just-fetched list (rather than
            // trusting the search-filtered $jilaCounts map above), so this
            // is correct even when $currentPrant was resolved via the
            // find() fallback below and never went through that map.
            $currentPrant['jila_count'] = count($jilas);
        }
        $prakhandCounts = $jilas ? array_column(
            $db->table('prakhands')->select('jila_id, COUNT(*) AS cnt')
                ->whereIn('jila_id', array_column($jilas, 'id'))->groupBy('jila_id')
                ->get()->getResultArray(),
            'cnt', 'jila_id'
        ) : [];
        foreach ($jilas as &$j) {
            $j['prakhand_count'] = (int) ($prakhandCounts[$j['id']] ?? 0);
        }
        unset($j);

        $selectedJilaId = (int) ($this->request->getGet('jila') ?: ($jilas[0]['id'] ?? 0));
        $currentJila    = null;
        foreach ($jilas as $j) {
            if ((int) $j['id'] === $selectedJilaId) {
                $currentJila = $j;
                break;
            }
        }

        $prakhands = $currentJila ? (new PrakhandModel())->forJila($selectedJilaId) : [];

        return view('masters/index', [
            'title'            => 'Masters',
            'tab'              => 'locations',
            'programmes'       => (new ProgrammeModel())->orderBy('sort_order', 'ASC')->findAll(),
            'prants'           => $prants,
            'search'           => $search,
            'selectedPrantId'  => $selectedPrantId,
            'currentPrant'     => $currentPrant,
            'jilas'            => $jilas,
            'selectedJilaId'   => $selectedJilaId,
            'currentJila'      => $currentJila,
            'prakhands'        => $prakhands,
            'allLanguages'     => I18n::languageOptions(),
            // The raw configured set (empty if nothing's been chosen yet) —
            // deliberately not PrantModel::languageCodes()'s all-13 fallback,
            // so this panel doesn't show every language pre-checked when
            // really none have been configured for this Prant.
            'prantLanguages'   => $currentPrant && ! empty($currentPrant['languages'])
                ? array_values(array_filter(array_map('trim', explode(',', $currentPrant['languages']))))
                : [],
        ]);
    }

    public function editProgramme($id)
    {
        $id = (int) $id;

        $programme = (new ProgrammeModel())->find($id);
        if (! $programme) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('masters/edit_programme', ['title' => 'Edit Programme', 'programme' => $programme]);
    }

    public function updateProgramme($id)
    {
        $id = (int) $id;

        $model = new ProgrammeModel();
        if (! $model->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $mode = $this->request->getPost('mode');
        $model->update($id, [
            'name'         => $this->request->getPost('name'),
            'mode'         => $mode,
            'rate'         => $mode === 'min' ? (float) $this->request->getPost('rate') : null,
            'is_recurring' => $this->request->getPost('is_recurring') ? 1 : 0,
            'status'       => $this->request->getPost('status') ?: 'active',
        ]);

        return redirect()->to('/admin/masters?tab=programmes')->with('success', 'Programme updated.');
    }

    public function newProgramme()
    {
        return view('masters/new_programme', ['title' => 'New Programme']);
    }

    public function createProgramme()
    {
        $rules = [
            'code' => 'required|alpha_dash|max_length[10]|is_unique[programmes.code]',
            'name' => 'required|min_length[2]|max_length[100]',
            'mode' => 'required|in_list[min,open]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $mode = $this->request->getPost('mode');
        (new ProgrammeModel())->insert([
            'code'         => strtolower($this->request->getPost('code')),
            'name'         => $this->request->getPost('name'),
            'mode'         => $mode,
            'rate'         => $mode === 'min' ? (float) $this->request->getPost('rate') : null,
            'is_recurring' => $this->request->getPost('is_recurring') ? 1 : 0,
            'status'       => 'active',
            'sort_order'   => (new ProgrammeModel())->countAll() + 1,
        ]);

        return redirect()->to('/admin/masters?tab=programmes')->with('success', 'Programme created.');
    }

    public function deleteProgramme($id)
    {
        $id = (int) $id;

        if (! (new ProgrammeModel())->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $enrolCount = db_connect()->table('enrolments')->where('programme_id', $id)->countAllResults();
        if ($enrolCount > 0) {
            return redirect()->to('/admin/masters?tab=programmes')
                ->with('error', "Can't delete — {$enrolCount} enrolment(s) use this programme. Set it to Inactive instead.");
        }

        (new ProgrammeModel())->delete($id);

        return redirect()->to('/admin/masters?tab=programmes')->with('success', 'Programme deleted.');
    }

    /** Downloadable .xlsx sample for the Prant/Jila/Prakhand bulk import. */
    public function downloadLocationTemplate()
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Locations');
        $sheet->fromArray(['Prant', 'Jila', 'Prakhand'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->fromArray([
            ['Dakshin Karnataka', 'Bengaluru', 'Bengaluru City East'],
            ['Dakshin Karnataka', 'Bengaluru', 'Bengaluru City West'],
            ['Dakshin Karnataka', 'Mysuru', 'Mysuru North'],
        ], null, 'A2');
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(28);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['How to fill this template'],
            ['One row per Prakhand — repeat the same Prant and Jila name on every row it belongs to.'],
            ['A Prant, Jila or Prakhand that already exists (exact name match) is reused, not duplicated.'],
            ['All three columns are required on every row.'],
            ['Delete the sample rows on the Locations sheet before importing your own data.'],
        ], null, 'A1');
        $notes->getColumnDimension('A')->setWidth(95);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $body = ob_get_clean();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="location-hierarchy-template.xlsx"')
            ->setBody($body);
    }

    /**
     * Imports a Prant/Jila/Prakhand hierarchy from an uploaded spreadsheet
     * (the format downloadLocationTemplate() produces). A row whose
     * Prant/Jila/Prakhand name already exists is reused rather than
     * duplicated, so the same file can be re-uploaded safely to add only
     * what's new.
     */
    public function bulkImportLocations()
    {
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->to('/admin/masters?tab=locations')->with('error', 'Please choose a file to import.');
        }
        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->to('/admin/masters?tab=locations')->with('error', 'Unsupported file type — upload the .xlsx template.');
        }

        try {
            $rows = IOFactory::load($file->getTempName())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return redirect()->to('/admin/masters?tab=locations')->with('error', "Couldn't read that file — is it a valid Excel/CSV file?");
        }

        array_shift($rows); // header row

        $prantModel    = new PrantModel();
        $jilaModel     = new JilaModel();
        $prakhandModel = new PrakhandModel();

        $prantCache = [];    // name => id
        $jilaCache  = [];    // "prantId|name" => id
        $seenPrakhand = [];  // "jilaId|name" => true, to skip repeat lookups within this file

        $prantsCreated = $jilasCreated = $prakhandsCreated = $skippedExisting = $skippedInvalid = 0;

        foreach ($rows as $row) {
            $prantName    = trim((string) ($row[0] ?? ''));
            $jilaName     = trim((string) ($row[1] ?? ''));
            $prakhandName = trim((string) ($row[2] ?? ''));

            if ($prantName === '' && $jilaName === '' && $prakhandName === '') {
                continue; // blank row
            }
            if ($prantName === '' || $jilaName === '' || $prakhandName === '') {
                $skippedInvalid++;
                continue;
            }

            if (! isset($prantCache[$prantName])) {
                $existing = $prantModel->where('name', $prantName)->first();
                if ($existing) {
                    $prantCache[$prantName] = $existing['id'];
                } else {
                    $prantCache[$prantName] = $prantModel->insert(['name' => $prantName], true);
                    $prantsCreated++;
                }
            }
            $prantId = $prantCache[$prantName];

            $jilaKey = $prantId . '|' . $jilaName;
            if (! isset($jilaCache[$jilaKey])) {
                $existing = $jilaModel->where('prant_id', $prantId)->where('name', $jilaName)->first();
                if ($existing) {
                    $jilaCache[$jilaKey] = $existing['id'];
                } else {
                    $jilaCache[$jilaKey] = $jilaModel->insert(['prant_id' => $prantId, 'name' => $jilaName], true);
                    $jilasCreated++;
                }
            }
            $jilaId = $jilaCache[$jilaKey];

            $prakhandKey = $jilaId . '|' . $prakhandName;
            if (isset($seenPrakhand[$prakhandKey])) {
                $skippedExisting++;
                continue;
            }
            $seenPrakhand[$prakhandKey] = true;

            if ($prakhandModel->where('jila_id', $jilaId)->where('name', $prakhandName)->first()) {
                $skippedExisting++;
                continue;
            }
            $prakhandModel->insert(['jila_id' => $jilaId, 'name' => $prakhandName]);
            $prakhandsCreated++;
        }

        (new AuditLogModel())->record($this->currentUser()->id, 'locations_bulk_imported', null, null, [
            'prants' => $prantsCreated, 'jilas' => $jilasCreated, 'prakhands' => $prakhandsCreated,
        ]);

        $summary = "Imported {$prantsCreated} new Prant(s), {$jilasCreated} new Jila(s) and {$prakhandsCreated} new Prakhand(s).";
        if ($skippedExisting > 0) {
            $summary .= " {$skippedExisting} row(s) already existed and were left as-is.";
        }
        if ($skippedInvalid > 0) {
            $summary .= " {$skippedInvalid} row(s) were missing a Prant, Jila or Prakhand name and were skipped.";
        }

        return redirect()->to('/admin/masters?tab=locations')->with('success', $summary);
    }

    public function addPrant()
    {
        $rules = ['name' => 'required|min_length[2]|max_length[100]|is_unique[prants.name]'];
        if (! $this->validate($rules)) {
            return redirect()->to('/admin/masters?tab=locations')->with('errors', $this->validator->getErrors());
        }

        (new PrantModel())->insert(['name' => $this->request->getPost('name')]);

        return redirect()->to('/admin/masters?tab=locations')->with('success', 'Prant added.');
    }

    public function addJila()
    {
        $prantId = (int) $this->request->getPost('prant_id');
        $rules   = ['name' => 'required|min_length[2]|max_length[100]'];
        if (! $this->validate($rules)) {
            return redirect()->to('/admin/masters?tab=locations&prant=' . $prantId)->with('errors', $this->validator->getErrors());
        }

        $jilaId = (new JilaModel())->insert(['prant_id' => $prantId, 'name' => $this->request->getPost('name')], true);

        return redirect()->to('/admin/masters?tab=locations&prant=' . $prantId . '&jila=' . $jilaId)->with('success', 'Jila added.');
    }

    public function addPrakhand()
    {
        $jilaId  = (int) $this->request->getPost('jila_id');
        $prantId = (int) $this->request->getPost('prant_id');
        $rules   = ['name' => 'required|min_length[2]|max_length[150]'];
        if (! $this->validate($rules)) {
            return redirect()->to('/admin/masters?tab=locations&prant=' . $prantId . '&jila=' . $jilaId)->with('errors', $this->validator->getErrors());
        }

        (new PrakhandModel())->insert(['jila_id' => $jilaId, 'name' => $this->request->getPost('name')]);

        return redirect()->to('/admin/masters?tab=locations&prant=' . $prantId . '&jila=' . $jilaId)->with('success', 'Prakhand added.');
    }

    public function renamePrant($id)
    {
        $id = (int) $id;

        $model = new PrantModel();
        if (! $model->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = ['name' => "required|min_length[2]|max_length[100]|is_unique[prants.name,id,{$id}]"];
        if (! $this->validate($rules)) {
            return redirect()->to('/admin/masters?tab=locations&prant=' . $id)->with('errors', $this->validator->getErrors());
        }

        // skipValidation: already validated above with the real $id
        // interpolated; the model's own is_unique[...,{id}] rule needs 'id'
        // in the data AND its own rule to resolve that placeholder (CI4
        // throws otherwise) — simpler to rely on the manual check just done.
        $model->skipValidation(true)->update($id, ['name' => $this->request->getPost('name')]);

        return redirect()->to('/admin/masters?tab=locations&prant=' . $id)->with('success', 'Prant renamed.');
    }

    /** Sets which languages this Prant's enrolment form offers (pinned to the top of the full list). */
    public function updatePrantLanguages($id)
    {
        $id = (int) $id;

        $model = new PrantModel();
        if (! $model->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $submitted = (array) $this->request->getPost('languages');
        $codes     = array_values(array_intersect($submitted, I18n::LANGUAGES));

        $model->skipValidation(true)->update($id, ['languages' => $codes ? implode(',', $codes) : null]);

        return redirect()->to('/admin/masters?tab=locations&prant=' . $id)->with('success', 'Languages updated.');
    }

    public function deletePrant($id)
    {
        $id = (int) $id;

        if (! (new PrantModel())->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db          = db_connect();
        $jilaCount   = $db->table('jilas')->where('prant_id', $id)->countAllResults();
        $userCount   = $db->table('users')->where('prant_id', $id)->countAllResults();
        $enrolCount  = $db->table('enrolments')->where('prant_id', $id)->countAllResults();

        if ($jilaCount > 0 || $userCount > 0 || $enrolCount > 0) {
            return redirect()->to('/admin/masters?tab=locations')
                ->with('error', "Can't delete — this Prant still has {$jilaCount} Jila(s), {$userCount} user(s) and {$enrolCount} enrolment(s) attached. Reassign or remove those first.");
        }

        (new PrantModel())->delete($id);
        db_connect()->table('gateway_credentials')->where('prant_id', $id)->delete();

        return redirect()->to('/admin/masters?tab=locations')->with('success', 'Prant deleted.');
    }

    public function renameJila($id)
    {
        $id = (int) $id;

        $model = new JilaModel();
        $jila  = $model->find($id);
        if (! $jila) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = ['name' => 'required|min_length[2]|max_length[100]'];
        if (! $this->validate($rules)) {
            return redirect()->to('/admin/masters?tab=locations&prant=' . $jila['prant_id'] . '&jila=' . $id)->with('errors', $this->validator->getErrors());
        }

        $model->update($id, ['name' => $this->request->getPost('name')]);

        return redirect()->to('/admin/masters?tab=locations&prant=' . $jila['prant_id'] . '&jila=' . $id)->with('success', 'Jila renamed.');
    }

    public function deleteJila($id)
    {
        $id = (int) $id;

        $jila = (new JilaModel())->find($id);
        if (! $jila) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db             = db_connect();
        $prakhandCount  = $db->table('prakhands')->where('jila_id', $id)->countAllResults();
        $userCount      = $db->table('users')->where('jila_id', $id)->countAllResults();
        $enrolCount     = $db->table('enrolments')->where('jila_id', $id)->countAllResults();

        if ($prakhandCount > 0 || $userCount > 0 || $enrolCount > 0) {
            return redirect()->to('/admin/masters?tab=locations&prant=' . $jila['prant_id'])
                ->with('error', "Can't delete — this Jila still has {$prakhandCount} Prakhand(s), {$userCount} user(s) and {$enrolCount} enrolment(s) attached.");
        }

        (new JilaModel())->delete($id);

        return redirect()->to('/admin/masters?tab=locations&prant=' . $jila['prant_id'])->with('success', 'Jila deleted.');
    }

    public function renamePrakhand($id)
    {
        $id = (int) $id;

        $model    = new PrakhandModel();
        $prakhand = $model->find($id);
        if (! $prakhand) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $prantId = (int) $this->request->getPost('prant_id');
        $backTo  = '/admin/masters?tab=locations&prant=' . $prantId . '&jila=' . $prakhand['jila_id'];
        $rules   = ['name' => 'required|min_length[2]|max_length[150]'];
        if (! $this->validate($rules)) {
            return redirect()->to($backTo)->with('errors', $this->validator->getErrors());
        }

        $model->update($id, ['name' => $this->request->getPost('name')]);

        return redirect()->to($backTo)->with('success', 'Prakhand renamed.');
    }

    public function deletePrakhand($id)
    {
        $id = (int) $id;

        $prakhand = (new PrakhandModel())->find($id);
        if (! $prakhand) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $prantId = (int) $this->request->getPost('prant_id');
        $backTo  = '/admin/masters?tab=locations&prant=' . $prantId . '&jila=' . $prakhand['jila_id'];
        $db         = db_connect();
        $userCount  = $db->table('users')->where('prakhand_id', $id)->countAllResults();
        $enrolCount = $db->table('enrolments')->where('prakhand_id', $id)->countAllResults();

        if ($userCount > 0 || $enrolCount > 0) {
            return redirect()->to($backTo)
                ->with('error', "Can't delete — {$userCount} user(s) and {$enrolCount} enrolment(s) reference this Prakhand.");
        }

        (new PrakhandModel())->delete($id);

        return redirect()->to($backTo)->with('success', 'Prakhand deleted.');
    }
}
