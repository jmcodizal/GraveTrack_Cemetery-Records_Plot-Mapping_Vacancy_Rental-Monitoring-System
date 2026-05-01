<?php

namespace App\Models;

use CodeIgniter\Model;

class ContactModel extends Model
{
    protected $table            = 'contacts';
    protected $primaryKey      = 'contact_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['deceased_id', 'contact_person', 'contact_number'];
    protected $useTimestamps = false;

    public function getContactsByDeceasedId($deceasedId)
    {
        return $this->where('deceased_id', $deceasedId)->findAll();
    }

    public function deleteByDeceasedId($deceasedId)
    {
        return $this->where('deceased_id', $deceasedId)->delete();
    }
}
