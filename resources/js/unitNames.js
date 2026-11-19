export const unitNameKeys = {
    202: 'small_cargo',
    203: 'large_cargo',
    204: 'light_fighter',
    205: 'heavy_fighter',
    206: 'cruiser',
    207: 'battleship',
    208: 'colony_ship',
    209: 'recycler',
    210: 'espionage_probe',
    211: 'bomber',
    212: 'solar_satellite',
    213: 'destroyer',
    214: 'deathstar',
    215: 'battlecruiser',
    216: 'supernova',
    217: 'planetary_destroyer',
};

export function translateUnitName(code, translate) {
    const key = unitNameKeys[code];

    return key ? translate(`unitNames.${key}`) : translate('fleet.ship', { code });
}
