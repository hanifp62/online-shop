const normalize = (value) => String(value ?? '').normalize('NFC').toLocaleLowerCase();

function isOneEditOrTransposition(query, candidate) {
    const left = Array.from(query);
    const right = Array.from(candidate);
    if (Math.abs(left.length - right.length) > 1) return false;

    if (left.length === right.length) {
        const differences = [];
        for (let index = 0; index < left.length; index++) {
            if (left[index] !== right[index]) differences.push(index);
            if (differences.length > 2) return false;
        }
        if (differences.length === 1) return true;
        return differences.length === 2
            && differences[1] === differences[0] + 1
            && left[differences[0]] === right[differences[1]]
            && left[differences[1]] === right[differences[0]];
    }

    const shorter = left.length < right.length ? left : right;
    const longer = left.length < right.length ? right : left;
    let shortIndex = 0;
    let longIndex = 0;
    let skipped = false;
    while (shortIndex < shorter.length && longIndex < longer.length) {
        if (shorter[shortIndex] === longer[longIndex]) {
            shortIndex++;
            longIndex++;
        } else if (skipped) {
            return false;
        } else {
            skipped = true;
            longIndex++;
        }
    }
    return true;
}

export function searchMatches(items, query, limit = Infinity) {
    const normalized = normalize(query).trim();
    if (!normalized) return [];
    const substringMatches = items.filter((item) => normalize(item.haystack).includes(normalized));
    if (substringMatches.length) return substringMatches.slice(0, limit);

    const words = normalized.split(/\s+/u);
    if (words.length !== 1 || Array.from(normalized).length < 4) return [];
    return items.filter((item) => normalize(item.haystack).split(/[^\p{L}\p{N}]+/u)
        .some((word) => word && isOneEditOrTransposition(normalized, word))).slice(0, limit);
}
