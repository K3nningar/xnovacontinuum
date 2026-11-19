export function normalizeFleetMission(data) {
    const payload = {
        ...data,
        ships: Object.fromEntries(Object.entries(data.ships ?? {}).map(([code, count]) => [code, Number(count ?? 0)])),
    };

    if (data.target_type === 'moon' && data.moon_orbit !== null && data.moon_orbit !== '') {
        payload.moon_orbit = Number(data.moon_orbit);
    } else {
        delete payload.moon_orbit;
    }
    if (data.origin_target_type === 'moon' && data.origin_moon_orbit !== null && data.origin_moon_orbit !== '') {
        payload.origin_moon_orbit = Number(data.origin_moon_orbit);
    } else {
        delete payload.origin_moon_orbit;
    }

    return payload;
}
