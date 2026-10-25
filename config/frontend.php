<?php

return [
    'enabled' => env('ZAFIRA_REACT_ENABLED', true) === true,
    // A page enters this allowlist only with native rendering and parity tests.
    'pages' => [
        'Catalog/Index',
        'Auth/Login',
        'Auth/Register',
        'Auth/ForgotPassword',
        'Admin/AuthEvents',
        'Admin/OperationsSummary',
        'Account/Show',
        'Admin/Login',
        'Admin/TotpChallenge',
        'Auth/TotpChallenge',
        'Auth/ResetPassword',
        'Account/TotpEnrol',
        'Account/TotpRecovery',
        'Admin/TotpEnrol',
        'Admin/TotpRecovery',
        'Account/CartReminderUnsubscribe',
        'Storefront/Home',
        'Products/Show',
        'Shop/Index',
        'Admin/Dashboard',
        'Admin/Profile',
    ],
];
