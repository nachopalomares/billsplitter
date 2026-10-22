<?php
require_once __DIR__ . '/../includes/functions.php';

function expectSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

expectSame(10, moneyToCents('0.10'), 'Decimal cents should be parsed exactly.');
expectSame(1299, moneyToCents('12.99'), 'Prices should retain both decimal digits.');
foreach (['-1', '1.001', '1e3', '', [], '100000000'] as $invalid) {
    try {
        moneyToCents($invalid);
    } catch (InvalidArgumentException $error) {
        continue;
    }
    throw new RuntimeException('An invalid price was accepted.');
}

$people = [
    ['participant_id' => 1, 'name' => 'A'],
    ['participant_id' => 2, 'name' => 'B'],
    ['participant_id' => 3, 'name' => 'C'],
];
$items = [10 => ['price' => '10.00'], 11 => ['price' => '0.01'], 12 => ['price' => '5.00']];
$assignments = [
    ['item_id' => 10, 'participant_id' => 3],
    ['item_id' => 10, 'participant_id' => 1],
    ['item_id' => 10, 'participant_id' => 2],
    ['item_id' => 10, 'participant_id' => 2],
    ['item_id' => 11, 'participant_id' => 2],
    ['item_id' => 99, 'participant_id' => 1],
    ['item_id' => 10, 'participant_id' => 99],
];
$amounts = calculateParticipantAmounts($items, $people, $assignments);
expectSame([334, 334, 333], array_column($amounts, 'amount_cents'), 'Shares must reconcile and ignore duplicate or unrelated assignments.');
expectSame(1001, array_sum(array_column($amounts, 'amount_cents')), 'Unassigned items must not count as assigned.');
expectSame([0, 0, 0], array_column(calculateParticipantAmounts($items, $people, []), 'amount_cents'), 'No assignments means no amount due.');
echo "Money checks passed.\n";
