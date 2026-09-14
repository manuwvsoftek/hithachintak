<?php

declare(strict_types=1);

namespace App\Libraries\Enrolment;

use App\Entities\Enrolment;
use App\Models\UserModel;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * Renders a downloadable PDF of a paid enrolment's receipt, in the
 * member's chosen language.
 *
 * Indic scripts need real OpenType shaping (conjuncts, matras, reordering),
 * which dompdf does not do at all and which the current Google-Fonts-served
 * Noto Sans static builds trip up mPDF's own OTL parser on (unsupported
 * GPOS lookup types). What actually works, verified glyph-by-glyph with
 * PyMuPDF rendering rather than just "no exception thrown": mPDF's own
 * bundled FreeSerif/Lohit-Kannada/Pothana2000/Eeyek fonts for Devanagari,
 * Kannada, Telugu and Meetei Mayek, plus the wider Lohit family (same
 * vintage, same OpenType table style) for the remaining scripts. FreeSerif
 * itself was tried for those remaining scripts first and silently produced
 * tofu for Gujarati/Bengali/Tamil and mis-shaped Gurmukhi, hence the mix.
 */
class ReceiptPdfGenerator
{
    private const FONT_DIR = __DIR__ . '/fonts/';

    /** Language code => mPDF font family to use for that script. */
    private const LANGUAGE_FONT_FAMILY = [
        'en'  => 'notosans',
        'hi'  => 'freeserif',       // Devanagari, bundled with mPDF
        'mr'  => 'freeserif',       // Marathi is also written in Devanagari
        'kn'  => 'lohitkannada',    // bundled with mPDF
        'te'  => 'pothana2000',     // bundled with mPDF
        'mni' => 'eeyekunicode',    // Meetei Mayek, bundled with mPDF
        'gu'  => 'lohitgujarati',
        'bn'  => 'lohitbengali',
        'as'  => 'lohitassamese',
        'ta'  => 'lohittamil',
        'or'  => 'lohitodia',
        'pa'  => 'lohitgurmukhi',
        'ml'  => 'lohitmalayalam',
    ];

    /** Custom font families not already bundled with mPDF. Filenames are relative to FONT_DIR. */
    private const CUSTOM_FONTS = [
        'notosans'       => ['R' => 'NotoSans-Regular.ttf', 'B' => 'NotoSans-Bold.ttf'],
        'lohitgujarati'  => ['R' => 'Lohit-Gujarati.ttf', 'useOTL' => 0xFF],
        'lohitbengali'   => ['R' => 'Lohit-Bengali.ttf', 'useOTL' => 0xFF],
        'lohitassamese'  => ['R' => 'Lohit-Assamese.ttf', 'useOTL' => 0xFF],
        'lohittamil'     => ['R' => 'Lohit-Tamil.ttf', 'useOTL' => 0xFF],
        'lohitodia'      => ['R' => 'Lohit-Odia.ttf', 'useOTL' => 0xFF],
        'lohitgurmukhi'  => ['R' => 'Lohit-Gurmukhi.ttf', 'useOTL' => 0xFF],
        'lohitmalayalam' => ['R' => 'Lohit-Malayalam.ttf', 'useOTL' => 0xFF],
    ];

    public function __construct(
        private readonly UserModel $users = new UserModel(),
        private readonly \App\Models\EnrolmentFamilyMemberModel $familyMembers = new \App\Models\EnrolmentFamilyMemberModel(),
    ) {
        helper('app');
    }

    public function generate(Enrolment $enrolment, array $member, array $programme, array $prant): string
    {
        $collector  = $this->users->find($enrolment->collected_by_user_id);
        $fontFamily = self::LANGUAGE_FONT_FAMILY[$enrolment->language] ?? 'notosans';

        $html = view('receipts/pdf', [
            'enrolment'     => $enrolment,
            'member'        => $member,
            'programme'     => $programme,
            'prant'         => $prant,
            't'             => I18n::t($enrolment->language),
            'trustName'     => I18n::trustName($enrolment->language),
            'trustPan'      => TrustInfo::PAN,
            'collectedBy'   => $collector->name ?? '—',
            'fontFamily'    => $fontFamily,
            'householdRows' => $this->familyMembers->householdRows($enrolment->id, $member),
        ]);

        $defaultFontDirs  = (new ConfigVariables())->getDefaults()['fontDir'];
        $defaultFontData  = (new FontVariables())->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode'             => 'UTF-8',
            'format'           => 'A5',
            'tempDir'          => WRITEPATH . 'fonts_cache',
            'fontDir'          => array_merge($defaultFontDirs, [self::FONT_DIR]),
            'fontdata'         => $defaultFontData + self::CUSTOM_FONTS,
            'default_font'     => $fontFamily,
            // Falls back to a Latin font for glyphs the script-specific font
            // doesn't carry (receipt numbers, dates, PAN) instead of tofu.
            'useSubstitutions' => true,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /** Filesystem/download-safe filename, e.g. HC-2026-000001.pdf */
    public function filename(Enrolment $enrolment): string
    {
        return str_replace('/', '-', $enrolment->receipt_no) . '.pdf';
    }
}
