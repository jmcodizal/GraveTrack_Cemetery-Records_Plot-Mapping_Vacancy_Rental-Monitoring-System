<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey      = 'user_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['username', 'password', 'email', 'created_at'];
    protected $useTimestamps   = false;

    public function getUserByUsername($username)
    {
        return $this->where('username', $username)->first();
    }

    public function verifyLogin($username, $password)
    {
        $user = $this->getUserByUsername($username);
        
        if (!$user) {
            return false;
        }

        $stored = $user['password'];
        return password_verify($password, $stored) || hash_equals($stored, $password);
    }
}
