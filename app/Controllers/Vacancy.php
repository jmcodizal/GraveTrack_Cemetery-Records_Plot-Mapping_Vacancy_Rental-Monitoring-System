<?php

namespace App\Controllers;

use App\Models\PlotModel;

class Vacancy extends BaseController
{
    public function index()
    {
        // Check if user is logged in
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }

        $sectionFilter = $this->request->getGet('section') ?? '';
        $typeFilter = $this->request->getGet('type') ?? '';

        $plotModel = new PlotModel();
        $plots = $plotModel->getFilteredPlots($sectionFilter, $typeFilter);

        // Get stats
        $stats = [
            'vacant' => $plotModel->countByStatus('Vacant'),
            'occupied' => $plotModel->countByStatus('Occupied'),
            'total' => $plotModel->countAll(),
        ];

        $data = [
            'plots' => $plots,
            'stats' => $stats,
            'sectionFilter' => $sectionFilter,
            'typeFilter' => $typeFilter,
        ];

        return view('vacancy', $data);
    }
}
