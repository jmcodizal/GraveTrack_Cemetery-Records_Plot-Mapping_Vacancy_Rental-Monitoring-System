<?php

namespace App\Controllers;

use App\Models\DeceasedModel;

class BurialRecords extends BaseController
{
    public function index()
    {
        // Check if user is logged in
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }

        $searchFilter = $this->request->getGet('search') ?? '';

        $deceasedModel = new DeceasedModel();
        
        if (!empty($searchFilter)) {
            $records = $deceasedModel->search($searchFilter);
        } else {
            $records = $deceasedModel->getAllWithContacts();
        }

        $data = [
            'records' => $records,
            'searchFilter' => $searchFilter,
        ];

        return view('burial_records', $data);
    }
}
