/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.1-alpha
 * Revision : 34ddc84
 * Description : Provides translated messages for the es buildings interface.
 */

export default {
  "catalogFilters": {
    "all": "Todo",
    "information": "Información",
    "combat": "Combate",
    "propulsion": "Propulsión",
    "energy": "Energía",
    "civilian": "Civil",
    "artillery": "Artillería",
    "shields": "Escudos",
    "missiles": "Misiles",
    "economy": "Economía",
    "science": "Ciencia",
    "defence": "Defensa",
    "command": "Mando",
    "other": "Otros",
    "unitCost": "Coste por unidad"
  },
  "buildingFilters": {
    "label": "Categorías",
    "all": "Todas las instalaciones",
    "production": "Producción",
    "storage": "Almacenamiento",
    "industry": "Industria",
    "research": "Investigación",
    "defence": "Defensa",
    "special": "Especial"
  },
  "buildings": {
    "metal": "Mina de metal",
    "crystal": "Mina de cristal",
    "deuterium": "Sintetizador de deuterio",
    "solar": "Planta solar",
    "fusion": "Planta de fusión",
    "robot_factory": "Fábrica de robots",
    "nano_factory": "Fábrica de nanitos",
    "hangar": "Astillero",
    "metal_storage": "Almacén de metal",
    "crystal_storage": "Almacén de cristal",
    "deuterium_storage": "Depósito de deuterio",
    "laboratory": "Laboratorio de investigación",
    "terraformer": "Terraformador",
    "missile_silo": "Silo de misiles",
    "title": "Edificios",
    "eyebrow": "INFRAESTRUCTURA PLANETARIA",
    "heading": "Tus edificios.",
    "subtitle": "Desarrolla la producción y gestiona el equilibrio energético.",
    "planet": "PLANETA",
    "planetarySite": "COLA PLANETARIA",
    "constructionCount": "{count} construcción(es) en cola",
    "noConstruction": "No hay construcciones en curso",
    "constructionRunning": "Construcción en curso",
    "nextConstruction": "Siguiente construcción en espera",
    "constructionWillAppear": "Los edificios en construcción aparecerán aquí.",
    "closeQueue": "Cerrar cola de construcción",
    "openQueue": "Abrir cola",
    "constructionQueue": "Cola de construcción",
    "queuesUsed": "{count} / 5 espacios de cola usados",
    "building": "{name} · nivel {level}",
    "inConstruction": "EN CONSTRUCCIÓN",
    "waiting": "EN ESPERA",
    "starts": "Inicio",
    "ends": "Fin",
    "remaining": "TIEMPO RESTANTE",
    "startsIn": "EMPIEZA EN",
    "noConstructionStarted": "No hay construcciones en curso. Inicia una mejora para verla aquí.",
    "energyBalance": "BALANCE ENERGÉTICO",
    "production": "Producción",
    "consumption": "Consumo",
    "mineEfficiency": "Rendimiento de minas",
    "productionSector": "SECTOR DE PRODUCCIÓN",
    "availableFacilities": "Instalaciones disponibles",
    "activity": "Actividad",
    "finalCost": "COSTE FINAL",
    "buildNextLevel": "Construir nivel {level}",
    "build": "Construir",
    "constructing": "Construyendo…",
    "fusionUse": "Consumo de deuterio según el nivel",
    "metalMine": "Mina de metal",
    "metalMineDescription": "Extrae el metal necesario para construir tu imperio.",
    "crystalMine": "Mina de cristal",
    "crystalMineDescription": "Produce cristal imprescindible para tecnologías y naves.",
    "deuteriumSynth": "Sintetizador de deuterio",
    "deuteriumDescription": "Refina deuterio de los océanos profundos del planeta.",
    "solarPlant": "Planta solar",
    "solarDescription": "Proporciona energía a las instalaciones planetarias.",
    "fusionPlant": "Planta de fusión",
    "fusionDescription": "Produce energía consumiendo deuterio.",
    "fusionRequirement": "Requiere sintetizador de deuterio nivel 5 y tecnología de energía nivel 3.",
    "robotFactory": "Fábrica de robots",
    "robotDescription": "Automatiza tareas y reduce los tiempos de construcción.",
    "robotEffect": "Cada nivel acorta las construcciones planetarias.",
    "nanoFactory": "Fábrica de nanitos",
    "nanoDescription": "Nanitos especializados aceleran los trabajos planetarios.",
    "nanoRequirement": "Requiere fábrica de robots nivel 10 y tecnología informática nivel 10.",
    "shipyard": "Astillero",
    "shipyardDescription": "Construye naves e instalaciones defensivas para tu imperio.",
    "shipyardRequirement": "Necesario para producir unidades espaciales.",
    "metalStorage": "Almacén de metal",
    "metalStorageDescription": "Aumenta la capacidad máxima de almacenamiento de metal del planeta.",
    "crystalStorage": "Almacén de cristal",
    "crystalStorageDescription": "Aumenta la capacidad máxima de almacenamiento de cristal del planeta.",
    "deutStorage": "Depósito de deuterio",
    "deutStorageDescription": "Aumenta la capacidad máxima de almacenamiento de deuterio del planeta.",
    "laboratoryDescription": "Investiga tecnologías y desarrolla el progreso científico de tu imperio.",
    "laboratoryRequirement": "Necesario para iniciar investigaciones. Solo se puede poner en cola una mejora del laboratorio a la vez.",
    "terraformerDescription": "Transforma la superficie y añade cinco campos disponibles por nivel.",
    "terraformerRequirement": "Requiere tecnología de energía nivel 12 y 1.000 unidades de energía.",
    "fieldsAdded": "campos adicionales",
    "allianceDeposit": "Depósito de alianza",
    "allianceDepositDescription": "Permitirá abastecer flotas aliadas durante las misiones.",
    "allianceDepositRequirement": "Disponible cuando se implementen las misiones de flota.",
    "missileSilo": "Silo de misiles",
    "siloDescription": "Almacena misiles interceptores e interplanetarios.",
    "siloRequirement": "Necesario para fabricar misiles."
  },
  "buildingActions": {
    "progressLabel": "Progreso de {name}, nivel {level}"
  },
  "buildingCatalog": {
    "metal": {
      "name": "Mina de metal",
      "description": "Extrae el metal necesario para construir tu imperio.",
      "unit": "metal / h"
    },
    "crystal": {
      "name": "Mina de cristal",
      "description": "Produce cristal imprescindible para tecnologías y naves.",
      "unit": "cristal / h"
    },
    "deuterium": {
      "name": "Sintetizador de deuterio",
      "description": "Refina deuterio de los océanos profundos del planeta.",
      "unit": "deuterio / h"
    },
    "solar": {
      "name": "Planta solar",
      "description": "Proporciona energía a las instalaciones planetarias.",
      "unit": "energía / h"
    },
    "fusion": {
      "name": "Planta de fusión",
      "description": "Produce energía consumiendo deuterio.",
      "unit": "energía / h",
      "requirement": "Requiere sintetizador de deuterio nivel 5 y tecnología de energía nivel 3."
    },
    "robot_factory": {
      "name": "Fábrica de robots",
      "description": "Automatiza tareas y reduce los tiempos de construcción.",
      "unit": "tiempo de construcción",
      "requirement": "Cada nivel acorta las construcciones planetarias."
    },
    "nano_factory": {
      "name": "Fábrica de nanitos",
      "description": "Nanitos especializados aceleran los trabajos planetarios.",
      "unit": "tiempo de construcción",
      "requirement": "Requiere fábrica de robots nivel 10 y tecnología informática nivel 10."
    },
    "hangar": {
      "name": "Astillero",
      "description": "Construye naves e instalaciones defensivas para tu imperio.",
      "unit": "producción",
      "requirement": "Necesario para producir unidades espaciales."
    },
    "metal_storage": {
      "name": "Almacén de metal",
      "description": "Aumenta la capacidad máxima de almacenamiento de metal del planeta.",
      "unit": "capacidad"
    },
    "crystal_storage": {
      "name": "Almacén de cristal",
      "description": "Aumenta la capacidad máxima de almacenamiento de cristal del planeta.",
      "unit": "capacidad"
    },
    "deuterium_storage": {
      "name": "Depósito de deuterio",
      "description": "Aumenta la capacidad máxima de almacenamiento de deuterio del planeta.",
      "unit": "capacidad"
    },
    "laboratory": {
      "name": "Laboratorio de investigación",
      "description": "Investiga tecnologías y desarrolla el progreso científico de tu imperio.",
      "unit": "investigación",
      "requirement": "Necesario para iniciar investigaciones. Solo se puede poner en cola una mejora del laboratorio a la vez."
    },
    "terraformer": {
      "name": "Terraformador",
      "description": "Transforma la superficie y añade cinco campos disponibles por nivel.",
      "unit": "campos adicionales",
      "requirement": "Requiere tecnología de energía nivel 12 y 1.000 unidades de energía."
    },
    "missile_silo": {
      "name": "Silo de misiles",
      "description": "Almacena misiles interceptores e interplanetarios.",
      "unit": "misiles",
      "requirement": "Necesario para fabricar misiles."
    }
  }
};
