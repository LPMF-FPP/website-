<?php

require __DIR__.'/vendor/autoload.php';

function removeNullLeaves(array $data): array
{
    $result = [];

    foreach ($data as $key => $value) {
        if ($value === null) {
            continue;
        }
        
        if (is_array($value)) {
            $cleaned = removeNullLeaves($value);
            if (!empty($cleaned)) {
                $result[$key] = $cleaned;
            }
        } else {
            $result[$key] = $value;
        }
    }

    return $result;
}

echo "Test 1: Nested array with values\n";
$input1 = [
    'security' => [
        'roles' => [
            'can_issue_number' => ['admin', 'supervisor']
        ]
    ]
];
$output1 = removeNullLeaves($input1);
print_r($output1);
echo "\n";

echo "Test 2: Array with null value\n";
$input2 = [
    'retention' => [
        'purge_after_days' => null,
        'storage_driver' => 'local'
    ]
];
$output2 = removeNullLeaves($input2);
print_r($output2);
echo "\n";

echo "Test 3: Array value (leaf) contains non-null values\n";
$input3 = ['roles' => ['admin', 'supervisor']];
$output3 = removeNullLeaves($input3);
print_r($output3);
