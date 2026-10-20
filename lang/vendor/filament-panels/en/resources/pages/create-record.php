<?php

// Plain wording on every "add" page: "Add Unit", "Save", "Save & add another".

return [

    'title' => 'Add :label',

    'breadcrumb' => 'Add',

    'form' => [

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

];
