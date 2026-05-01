<?php

namespace App\Models;

use CodeIgniter\Model;

class PlotModel extends Model
{
    protected $table            = 'plots';
    protected $primaryKey      = 'plot_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['block', 'section', 'lot', 'type', 'status', 'date_added'];
    protected $useTimestamps = false;

    public function getFilteredPlots($section = '', $type = '')
    {
        $builder = $this->builder();

        if (!empty($section)) {
            $builder->like('section', $section);
        }
        if (!empty($type)) {
            $builder->like('type', $type);
        }

        return $builder->get()->getResultArray();
    }

    public function getVacantPlots()
    {
        return $this->where('status', 'Vacant')
                    ->orderBy('block, section, lot')
                    ->find();
    }

    public function countByStatus($status)
    {
        return $this->where('status', $status)->countAllResults();
    }

    public function countAll()
    {
        return $this->countAll();
    }

    public function getPlotById($plotId)
    {
        return $this->find($plotId);
    }

    public function updateStatus($plotId, $status)
    {
        return $this->update($plotId, ['status' => $status]);
    }
}
