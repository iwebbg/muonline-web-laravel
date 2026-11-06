<?php

$configuredAccountIds = explode(',', (string) env('DEMO_ACCOUNTS', ''));
$demoAccountIds = [];

foreach ($configuredAccountIds as $configuredAccountId) {
    $accountId = strtolower(trim($configuredAccountId));

    if ($accountId !== '') {
        $demoAccountIds[] = $accountId;
    }
}

return [
    'enabled' => filter_var(
        env('DEMO_MODE', false),
        FILTER_VALIDATE_BOOL,
    ),

    'account_ids' => array_values(array_unique($demoAccountIds)),
];
