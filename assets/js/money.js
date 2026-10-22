const BillMoney = {
    toCents(value) {
        if (typeof value !== 'string' || !/^\d{1,8}(?:\.\d{1,2})?$/.test(value)) return null;
        const [whole, fraction = ''] = value.split('.');
        return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    },

    split(totalCents, people) {
        if (!Number.isSafeInteger(totalCents) || totalCents < 0 || !Number.isInteger(people) || people < 1) {
            throw new RangeError('Use a non-negative total in cents and a positive whole number of people.');
        }
        return { base: Math.floor(totalCents / people), extra: totalCents % people };
    }
};

if (typeof module !== 'undefined' && module.exports) module.exports = BillMoney;
