<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authorization Exception Messages
    |--------------------------------------------------------------------------
    |
    | These messages are used when authorization fails. You can customize
    | them to match your application's tone and security requirements.
    |
    | Available placeholders:
    | - :permission  - Single permission name
    | - :permissions - Comma-separated list of permission names
    | - :role        - Single role name
    | - :roles       - Comma-separated list of role names
    |
    | Security note: For production, consider using generic messages that
    | don't reveal which specific permissions or roles are required.
    |
    */

    'not_logged_in' => 'You must be logged in.',
    'not_eloquent_model' => 'Not an Eloquent model.',
    'missing_permission' => 'You do not have the :permission permission.',
    'missing_permissions' => 'You do not have the required permissions: :permissions.',
    'missing_role' => 'You do not have the :role role.',
    'missing_roles' => 'You do not have the required roles: :roles.',
    'missing_role_or_permission' => 'You do not have any of the required roles or permissions.',
];
