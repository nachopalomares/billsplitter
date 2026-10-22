const assert = require('node:assert/strict');
const money = require('../assets/js/money.js');

assert.equal(money.toCents('0.10'), 10);
assert.equal(money.toCents('12.99'), 1299);
for (const invalid of ['-1', '1.001', '1e3', '', '100000000']) {
    assert.equal(money.toCents(invalid), null);
}
assert.deepEqual(money.split(1000, 3), { base: 333, extra: 1 });
assert.deepEqual(money.split(1, 3), { base: 0, extra: 1 });
assert.deepEqual(money.split(0, 2), { base: 0, extra: 0 });
assert.throws(() => money.split(100, 0), RangeError);
assert.throws(() => money.split(100, 1.5), RangeError);
assert.throws(() => money.split(-1, 2), RangeError);
console.log('Money checks passed.');
