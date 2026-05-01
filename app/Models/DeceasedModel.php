<?php

namespace App\Models;

use CodeIgniter\Model;

class DeceasedModel extends Model
{
    protected $table            = 'deceased';
    protected $primaryKey      = 'deceased_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'full_name', 'birth_date', 'date_of_death', 'date_of_burial', 
        'gender', 'address', 'plot_id', 'burial_type', 'created_by'
    ];
    protected $useTimestamps = false;

    public function getAllWithContacts()
    {
        $sql = "SELECT d.*, c.contact_person, c.contact_number, p.plot_location 
                FROM deceased d 
                LEFT JOIN contacts c ON d.deceased_id = c.deceased_id
                LEFT JOIN plots p ON d.plot_id = p.plot_id
                ORDER BY d.date_of_burial DESC";
        
        $result = $this->db->query($sql);
        return $result->getResultArray();
    }

    public function search($keyword)
    {
        $sql = "SELECT d.*, c.contact_person, c.contact_number, p.plot_location 
                FROM deceased d 
                LEFT JOIN contacts c ON d.deceased_id = c.deceased_id
                LEFT JOIN plots p ON d.plot_id = p.plot_id
                WHERE d.full_name LIKE ? OR c.contact_person LIKE ? OR p.plot_location LIKE ?
                ORDER BY d.date_of_burial DESC";
        
        $keyword = "%{$keyword}%";
        $result = $this->db->query($sql, [$keyword, $keyword, $keyword]);
        return $result->getResultArray();
    }

    public function insertRecord($data)
    {
        // Insert deceased
        $this->db->table($this->table)->insert($data);
        $deceasedId = $this->db->insertID();

        return $deceasedId;
    }
}
