<?php

// Matches the "add" pages: "Add Unit", "Save", "Save & add another".

return [

    'single' => [

        'label' => 'Add :label',

        'modal' => [

            'heading' => 'Add :label',

            'actions' => [

                'create' => [
                    'label' => 'Save',
                ],

                'create_another' => [
                    'label' => 'Save & add another',
                ],

            ],

        ],

        'notifications' => [

            'created' => [
                'title' => 'Saved',
            ],

        ],

    ],

];
