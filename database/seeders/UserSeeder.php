<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         User::create([
            'name' => 'hyder',
            'email' => 'hyderalioffice9734@gmail.com',
            'password' => 'anas123@#',
            'role' => 'admin',
        ]);
    }
}
