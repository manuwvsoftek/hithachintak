<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\JilaModel;
use App\Models\PrakhandModel;

/** JSON endpoints backing the cascading Prant -> Jila -> Prakhand dropdowns. */
class LocationApiController extends BaseController
{
    public function jilas($prantId)
    {
        $prantId = (int) $prantId;

        return $this->response->setJSON((new JilaModel())->forPrant($prantId));
    }

    public function prakhands($jilaId)
    {
        $jilaId = (int) $jilaId;

        return $this->response->setJSON((new PrakhandModel())->forJila($jilaId));
    }
}
