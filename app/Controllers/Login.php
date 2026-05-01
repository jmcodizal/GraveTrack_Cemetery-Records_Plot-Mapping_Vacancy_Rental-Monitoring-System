<?php

namespace App\Controllers;

use App\Models\UserModel;

class Login extends BaseController
{
    public function index()
    {
        // Check if user is already logged in
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }

        return view('login');
    }

    public function auth()
    {
        header('Content-Type: application/json');

        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        if (!$username || !$password) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Missing fields'
            ])->setStatusCode(400);
        }

        $userModel = new UserModel();
        $user = $userModel->getUserByUsername($username);

        if ($user) {
            $stored = $user['password'];
            $isValid = password_verify($password, $stored) || hash_equals($stored, $password);
            
            if (!$isValid) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ])->setStatusCode(401);
            }

            // Set session
            session()->set('isLoggedIn', true);
            session()->set('userId', $user['user_id']);
            session()->set('username', $user['username']);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Login successful'
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Invalid credentials'
        ])->setStatusCode(401);
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
