<?php

namespace App\Controllers;

use App\Models\DeceasedModel;
use App\Models\PlotModel;
use App\Models\ContactModel;

class AddBurial extends BaseController
{
    public function index()
    {
        // Check if user is logged in
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }

        $plotModel = new PlotModel();
        $vacantPlots = $plotModel->getVacantPlots();

        $data = [
            'vacantPlots' => $vacantPlots,
        ];

        return view('adding_burial_records', $data);
    }

    public function save()
    {
        header('Content-Type: application/json');

        $fullName = $this->request->getPost('fullName');
        $dateOfBirth = $this->request->getPost('dateOfBirth');
        $dateOfDeath = $this->request->getPost('dateOfDeath');
        $dateOfBurial = $this->request->getPost('dateOfBurial');
        $gender = $this->request->getPost('gender');
        $contactPerson = $this->request->getPost('contactPerson');
        $contactNumber = $this->request->getPost('contactNumber');
        $address = $this->request->getPost('address');
        $plotId = $this->request->getPost('plotId');
        $burialType = $this->request->getPost('burialType');

        // Validate required fields
        if (empty($fullName) || empty($dateOfDeath) || empty($dateOfBurial) || empty($plotId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Please fill in all required fields (Full Name, Date of Death, Date of Burial, Plot)'
            ])->setStatusCode(400);
        }

        $plotModel = new PlotModel();
        $plot = $plotModel->getPlotById($plotId);

        if (!$plot || $plot['status'] !== 'Vacant') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Plot not found or not vacant'
            ])->setStatusCode(400);
        }

        $deceasedModel = new DeceasedModel();
        $contactModel = new ContactModel();

        // Start transaction
        $this->db->transStart();

        try {
            // Insert deceased
            $deceasedData = [
                'full_name' => $fullName,
                'birth_date' => $dateOfBirth ?: null,
                'date_of_death' => $dateOfDeath,
                'date_of_burial' => $dateOfBurial,
                'gender' => $gender,
                'address' => $address,
                'plot_id' => $plotId,
                'burial_type' => $burialType,
            ];

            $deceasedId = $deceasedModel->insert($deceasedData);

            // Insert contact if provided
            if (!empty($contactPerson)) {
                $contactData = [
                    'deceased_id' => $deceasedId,
                    'contact_person' => $contactPerson,
                    'contact_number' => $contactNumber,
                ];
                $contactModel->insert($contactData);
            }

            // Update plot status to Occupied
            $plotModel->update($plotId, ['status' => 'Occupied']);

            // Commit transaction
            $this->db->transCommit();

            return $this->response->setJSON([
                'success' => true,
                'message' => "Burial record saved! ID: $deceasedId - Plot marked as Occupied",
                'deceased_id' => $deceasedId
            ]);

        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function getVacantPlots()
    {
        header('Content-Type: application/json');

        $plotModel = new PlotModel();
        $plots = $plotModel->getVacantPlots();

        $formattedPlots = [];
        foreach ($plots as $plot) {
            $formattedPlots[] = [
                'plot_id' => $plot['plot_id'],
                'label' => 'Block ' . $plot['block'] . ', Section ' . $plot['section'] . ', Lot ' . $plot['lot'] . ' (' . $plot['type'] . ')',
                'block' => $plot['block'],
                'section' => $plot['section'],
                'lot' => $plot['lot'],
                'type' => $plot['type']
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'plots' => $formattedPlots,
            'count' => count($formattedPlots)
        ]);
    }
}
