<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Article;
class ArticlesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
             // Create 50 sample articles
       // Create 50 sample articles
        for ($i = 1; $i <= 50; $i++) {
            Article::create([
                'title' => 'Article Title ' . $i,
                'content' => 'This is the content of article ' . $i,
                'image' => 'https://via.placeholder.com/150?text=Image+' . $i, // Sample image URL
            ]);
        }
    }

}
