<?php

return [
    'departments' => [
        [
            'name' => 'College of Accountancy',
            'courses' => [
                ['name' => 'Bachelor of Science in Accountancy', 'majors' => []],
                ['name' => 'Bachelor of Science in Accounting Information', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Arts and Science',
            'courses' => [
                ['name' => 'Bachelor of Arts in Political Science', 'majors' => []],
                ['name' => 'Bachelor of Arts in Journalism', 'majors' => []],
                ['name' => 'Bachelor of Arts in Philosophy', 'majors' => []],
                ['name' => 'Bachelor of Science in Psychology', 'majors' => []],
                ['name' => 'Bachelor of Science in Social Work', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Business Education',
            'courses' => [
                ['name' => 'Bachelor of Science in Business Administration', 'majors' => [
                    'Human Resources Management',
                    'Financial Management',
                    'Marketing Management',
                ]],
                ['name' => 'Bachelor of Science in Office Administration', 'majors' => []],
                ['name' => 'Bachelor of Science in Hospitality Management', 'majors' => []],
                ['name' => 'Bachelor of Science in Tourism Management', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Criminology',
            'courses' => [
                ['name' => 'Bachelor of Science in Criminology', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Education',
            'courses' => [
                ['name' => 'Bachelor of Elementary Education', 'majors' => []],
                ['name' => 'Bachelor of Secondary Early Childhood Education', 'majors' => []],
                ['name' => 'Bachelor of Physical Education', 'majors' => []],
                ['name' => 'Bachelor of Secondary Education', 'majors' => [
                    'English',
                    'Filipino',
                    'Mathematics',
                    'Sciences',
                    'Social Studies',
                ]],
            ],
        ],
        [
            'name' => 'College of Engineering and Architecture',
            'courses' => [
                ['name' => 'Bachelor of Science in Civil Engineering', 'majors' => []],
                ['name' => 'Bachelor of Science in Electronics Engineering', 'majors' => []],
                ['name' => 'Bachelor of Science in Computer Engineering', 'majors' => []],
                ['name' => 'Bachelor of Science in Architecture', 'majors' => []],
                ['name' => 'Bachelor of Science in Geodetic Engineering', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Information Technology',
            'courses' => [
                ['name' => 'Bachelor of Science in Information Technology', 'majors' => []],
                ['name' => 'Bachelor of Library and Information Science', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Law',
            'courses' => [
                ['name' => 'Juris Doctor (Bachelor of Laws)', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Medicine and Allied Medical Programs',
            'courses' => [
                ['name' => 'Bachelor of Science in Medical Laboratory Science', 'majors' => []],
                ['name' => 'Bachelor of Science in Radiologic Technology', 'majors' => []],
                ['name' => 'Bachelor of Science in Pharmacy', 'majors' => []],
                ['name' => 'Bachelor of Science in Physical Therapy', 'majors' => []],
            ],
        ],
        [
            'name' => 'College of Nursing, Public Health and Midwifery',
            'courses' => [
                ['name' => 'Bachelor of Science in Nursing', 'majors' => []],
                ['name' => 'Bachelor of Science in Midwifery', 'majors' => []],
                ['name' => 'Bachelor of Science in Public Health', 'majors' => []],
            ],
        ],
    ],
    'year_levels' => ['1', '2', '3', '4'],
    'sections' => range('A', 'Z'),
];
