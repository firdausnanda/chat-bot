<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $books = [
            [
                'title' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
                'author' => 'Robert C. Martin',
                'rack_location' => 'A-101',
                'category' => 'Software Engineering',
                'description' => 'Even bad code can function. But if code isn\'t clean, it can bring a development organization to its knees.',
                'published_year' => '2008',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Artificial Intelligence: A Modern Approach',
                'author' => 'Stuart Russell, Peter Norvig',
                'rack_location' => 'B-205',
                'category' => 'Artificial Intelligence',
                'description' => 'The leading textbook in Artificial Intelligence, used in over 1400 universities in over 128 countries.',
                'published_year' => '2020',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'The Pragmatic Programmer: Your Journey to Mastery',
                'author' => 'David Thomas, Andrew Hunt',
                'rack_location' => 'A-102',
                'category' => 'Software Engineering',
                'description' => 'One of the most significant books in my life. Obsolete... except for the wisdom.',
                'published_year' => '2019',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Deep Learning',
                'author' => 'Ian Goodfellow, Yoshua Bengio, Aaron Courville',
                'rack_location' => 'B-210',
                'category' => 'Artificial Intelligence',
                'description' => 'An introduction to a broad range of topics in deep learning, covering mathematical and conceptual background.',
                'published_year' => '2016',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Sapiens: A Brief History of Humankind',
                'author' => 'Yuval Noah Harari',
                'rack_location' => 'H-301',
                'category' => 'History',
                'description' => 'Explores how biology and history have defined us and enhanced our understanding of what it means to be "human".',
                'published_year' => '2011',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'rack_location' => 'F-404',
                'category' => 'Science Fiction',
                'description' => 'Set on the desert planet Arrakis, Dune is the story of the boy Paul Atreides, heir to a noble family.',
                'published_year' => '1965',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Introduction to Algorithms',
                'author' => 'Thomas H. Cormen, Charles E. Leiserson, Ronald L. Rivest, Clifford Stein',
                'rack_location' => 'A-115',
                'category' => 'Computer Science',
                'description' => 'A comprehensive update of the leading algorithms text, with new material on matchings in bipartite graphs, online algorithms, machine learning, and other topics.',
                'published_year' => '2009',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('books')->insert($books);
    }
}
