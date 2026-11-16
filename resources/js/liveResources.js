export function projectPlanetResources(planet, serverTime) {
    const snapshot = planet.liveResources;
    const updatedAt = Date.parse(snapshot.updatedAt);
    const nextChange = snapshot.nextChangeAt ? Date.parse(snapshot.nextChangeAt) : Infinity;
    const elapsed = Math.max(0, Math.floor((Math.min(serverTime, nextChange, updatedAt + 60_000) - updatedAt) / 1000));
    const resources = {};

    for (const resource of ['metal', 'crystal', 'deuterium']) {
        const stock = Number(planet[resource]);
        const produced = Math.floor((snapshot.rates[resource] * elapsed + snapshot.remainders[resource]) / 3600);
        const limit = Math.floor(snapshot.capacities[resource] * 1.1);
        resources[resource] = stock + Math.min(produced, Math.max(0, limit - stock));
    }

    const consumption = Math.floor((snapshot.fusionUsage * elapsed + snapshot.fusionRemainder) / 3600);
    resources.deuterium = Math.max(0, resources.deuterium - consumption);

    return { ...planet, ...resources };
}
