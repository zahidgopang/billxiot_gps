<?php

/**
 * Built-in vehicle icon catalog for map marker customization.
 * Each icon maps to a shape renderer in public/js/vehicle-icon-shapes.js.
 */
return [
    'categories' => [
        'cars' => ['label_key' => 'icon_cat_cars', 'order' => 10],
        'trucks' => ['label_key' => 'icon_cat_trucks', 'order' => 20],
        'buses' => ['label_key' => 'icon_cat_buses', 'order' => 30],
        'motorcycles' => ['label_key' => 'icon_cat_motorcycles', 'order' => 40],
        'heavy_equipment' => ['label_key' => 'icon_cat_heavy', 'order' => 50],
        'emergency' => ['label_key' => 'icon_cat_emergency', 'order' => 60],
        'utility' => ['label_key' => 'icon_cat_utility', 'order' => 70],
        'marine' => ['label_key' => 'icon_cat_marine', 'order' => 80],
        'aviation' => ['label_key' => 'icon_cat_aviation', 'order' => 90],
        'agriculture' => ['label_key' => 'icon_cat_agriculture', 'order' => 100],
        'railway' => ['label_key' => 'icon_cat_railway', 'order' => 110],
        'assets' => ['label_key' => 'icon_cat_assets', 'order' => 120],
        'special' => ['label_key' => 'icon_cat_special', 'order' => 130],
        'generic' => ['label_key' => 'icon_cat_generic', 'order' => 140],
    ],

    /** @deprecated Use category — kept for backward compat */
    'legacy_group_map' => [
        'cars' => 'road',
        'trucks' => 'road',
        'buses' => 'road',
        'motorcycles' => 'road',
        'heavy_equipment' => 'special',
        'emergency' => 'emergency',
        'utility' => 'road',
        'marine' => 'special',
        'aviation' => 'special',
        'agriculture' => 'special',
        'railway' => 'special',
        'assets' => 'other',
        'special' => 'other',
        'generic' => 'other',
    ],

    'aliases' => [
        'police' => 'police_car',
        'pickup' => 'pickup_truck',
        'fire_truck' => 'fire_truck',
        'other' => 'default_vehicle',
    ],

    'icons' => [
        // Cars
        'car' => ['category' => 'cars', 'shape' => 'sedan', 'label' => 'Car', 'tags' => ['car', 'auto', 'vehicle']],
        'suv' => ['category' => 'cars', 'shape' => 'suv', 'label' => 'SUV', 'tags' => ['suv', '4x4']],
        'sedan' => ['category' => 'cars', 'shape' => 'sedan', 'label' => 'Sedan', 'tags' => ['sedan', 'car']],
        'hatchback' => ['category' => 'cars', 'shape' => 'hatchback', 'label' => 'Hatchback', 'tags' => ['hatchback', 'car']],
        'coupe' => ['category' => 'cars', 'shape' => 'coupe', 'label' => 'Coupe', 'tags' => ['coupe', 'car']],
        'convertible' => ['category' => 'cars', 'shape' => 'convertible', 'label' => 'Convertible', 'tags' => ['convertible', 'car']],
        'golf_cart' => ['category' => 'cars', 'shape' => 'golf_cart', 'label' => 'Golf Cart', 'tags' => ['golf', 'cart']],

        // Trucks
        'pickup_truck' => ['category' => 'trucks', 'shape' => 'pickup', 'label' => 'Pickup Truck', 'tags' => ['pickup', 'truck']],
        'truck' => ['category' => 'trucks', 'shape' => 'truck', 'label' => 'Truck', 'tags' => ['truck', 'lorry']],
        'semi_truck' => ['category' => 'trucks', 'shape' => 'semi', 'label' => 'Semi Truck', 'tags' => ['semi', 'trailer', 'truck']],
        'box_truck' => ['category' => 'trucks', 'shape' => 'box_truck', 'label' => 'Box Truck', 'tags' => ['box', 'truck']],
        'tanker_truck' => ['category' => 'trucks', 'shape' => 'tanker', 'label' => 'Tanker Truck', 'tags' => ['tanker', 'fuel']],
        'flatbed_truck' => ['category' => 'trucks', 'shape' => 'flatbed', 'label' => 'Flatbed Truck', 'tags' => ['flatbed', 'truck']],
        'dump_truck' => ['category' => 'trucks', 'shape' => 'dump_truck', 'label' => 'Dump Truck', 'tags' => ['dump', 'construction']],
        'trailer' => ['category' => 'trucks', 'shape' => 'trailer', 'label' => 'Trailer', 'tags' => ['trailer', 'haul']],

        // Buses & vans
        'van' => ['category' => 'buses', 'shape' => 'van', 'label' => 'Van', 'tags' => ['van']],
        'mini_van' => ['category' => 'buses', 'shape' => 'mini_van', 'label' => 'Mini Van', 'tags' => ['minivan', 'van']],
        'cargo_van' => ['category' => 'buses', 'shape' => 'cargo_van', 'label' => 'Cargo Van', 'tags' => ['cargo', 'van']],
        'bus' => ['category' => 'buses', 'shape' => 'bus', 'label' => 'Bus', 'tags' => ['bus', 'transit']],
        'school_bus' => ['category' => 'buses', 'shape' => 'school_bus', 'label' => 'School Bus', 'tags' => ['school', 'bus']],
        'coach_bus' => ['category' => 'buses', 'shape' => 'coach_bus', 'label' => 'Coach Bus', 'tags' => ['coach', 'bus']],

        // Motorcycles
        'motorcycle' => ['category' => 'motorcycles', 'shape' => 'motorcycle', 'label' => 'Motorcycle', 'tags' => ['motorcycle', 'bike']],
        'scooter' => ['category' => 'motorcycles', 'shape' => 'scooter', 'label' => 'Scooter', 'tags' => ['scooter']],
        'bicycle' => ['category' => 'motorcycles', 'shape' => 'bicycle', 'label' => 'Bicycle', 'tags' => ['bicycle', 'bike']],
        'atv' => ['category' => 'motorcycles', 'shape' => 'atv', 'label' => 'ATV', 'tags' => ['atv', 'quad']],

        // Heavy equipment
        'excavator' => ['category' => 'heavy_equipment', 'shape' => 'excavator', 'label' => 'Excavator', 'tags' => ['excavator', 'construction']],
        'bulldozer' => ['category' => 'heavy_equipment', 'shape' => 'bulldozer', 'label' => 'Bulldozer', 'tags' => ['bulldozer', 'construction']],
        'crane' => ['category' => 'heavy_equipment', 'shape' => 'crane', 'label' => 'Crane', 'label_key' => 'vehicle_type_crane', 'tags' => ['crane']],
        'loader' => ['category' => 'heavy_equipment', 'shape' => 'loader', 'label' => 'Loader', 'tags' => ['loader', 'construction']],
        'forklift' => ['category' => 'heavy_equipment', 'shape' => 'forklift', 'label' => 'Forklift', 'tags' => ['forklift', 'warehouse']],
        'road_roller' => ['category' => 'heavy_equipment', 'shape' => 'road_roller', 'label' => 'Road Roller', 'tags' => ['roller', 'construction']],
        'cement_mixer' => ['category' => 'heavy_equipment', 'shape' => 'cement_mixer', 'label' => 'Cement Mixer', 'tags' => ['cement', 'mixer']],

        // Emergency
        'ambulance' => ['category' => 'emergency', 'shape' => 'ambulance', 'label' => 'Ambulance', 'tags' => ['ambulance', 'emergency']],
        'police_car' => ['category' => 'emergency', 'shape' => 'police', 'label' => 'Police Car', 'tags' => ['police', 'emergency']],
        'fire_truck' => ['category' => 'emergency', 'shape' => 'fire_truck', 'label' => 'Fire Truck', 'tags' => ['fire', 'emergency']],
        'rescue_vehicle' => ['category' => 'emergency', 'shape' => 'rescue', 'label' => 'Rescue Vehicle', 'tags' => ['rescue', 'emergency']],

        // Utility
        'taxi' => ['category' => 'utility', 'shape' => 'taxi', 'label' => 'Taxi', 'tags' => ['taxi', 'cab']],
        'tow_truck' => ['category' => 'utility', 'shape' => 'tow_truck', 'label' => 'Tow Truck', 'tags' => ['tow', 'recovery']],
        'delivery_van' => ['category' => 'utility', 'shape' => 'delivery_van', 'label' => 'Delivery Van', 'tags' => ['delivery', 'van']],
        'garbage_truck' => ['category' => 'utility', 'shape' => 'garbage_truck', 'label' => 'Garbage Truck', 'tags' => ['garbage', 'waste']],
        'utility_truck' => ['category' => 'utility', 'shape' => 'utility_truck', 'label' => 'Utility Truck', 'tags' => ['utility', 'service']],
        'postal_vehicle' => ['category' => 'utility', 'shape' => 'postal', 'label' => 'Postal Vehicle', 'tags' => ['postal', 'mail']],
        'street_sweeper' => ['category' => 'utility', 'shape' => 'street_sweeper', 'label' => 'Street Sweeper', 'tags' => ['sweeper', 'street']],

        // Marine
        'boat' => ['category' => 'marine', 'shape' => 'boat', 'label' => 'Boat', 'tags' => ['boat', 'marine']],
        'speed_boat' => ['category' => 'marine', 'shape' => 'speed_boat', 'label' => 'Speed Boat', 'tags' => ['speedboat', 'marine']],
        'yacht' => ['category' => 'marine', 'shape' => 'yacht', 'label' => 'Yacht', 'tags' => ['yacht', 'marine']],
        'cargo_ship' => ['category' => 'marine', 'shape' => 'cargo_ship', 'label' => 'Cargo Ship', 'tags' => ['ship', 'cargo']],
        'fishing_boat' => ['category' => 'marine', 'shape' => 'fishing_boat', 'label' => 'Fishing Boat', 'tags' => ['fishing', 'boat']],

        // Aviation
        'airplane' => ['category' => 'aviation', 'shape' => 'airplane', 'label' => 'Airplane', 'tags' => ['airplane', 'aircraft']],
        'helicopter' => ['category' => 'aviation', 'shape' => 'helicopter', 'label' => 'Helicopter', 'tags' => ['helicopter', 'aircraft']],
        'drone' => ['category' => 'aviation', 'shape' => 'drone', 'label' => 'Drone', 'tags' => ['drone', 'uav']],

        // Agriculture
        'tractor' => ['category' => 'agriculture', 'shape' => 'tractor', 'label' => 'Tractor', 'tags' => ['tractor', 'farm']],
        'harvester' => ['category' => 'agriculture', 'shape' => 'harvester', 'label' => 'Harvester', 'tags' => ['harvester', 'farm']],
        'sprayer' => ['category' => 'agriculture', 'shape' => 'sprayer', 'label' => 'Sprayer', 'tags' => ['sprayer', 'farm']],

        // Railway
        'train' => ['category' => 'railway', 'shape' => 'train', 'label' => 'Train', 'tags' => ['train', 'rail']],
        'locomotive' => ['category' => 'railway', 'shape' => 'locomotive', 'label' => 'Locomotive', 'tags' => ['locomotive', 'rail']],

        // Assets
        'shipping_container' => ['category' => 'assets', 'shape' => 'container', 'label' => 'Shipping Container', 'tags' => ['container', 'asset']],
        'generator' => ['category' => 'assets', 'shape' => 'generator', 'label' => 'Generator', 'tags' => ['generator', 'asset']],
        'fuel_tank' => ['category' => 'assets', 'shape' => 'fuel_tank', 'label' => 'Fuel Tank', 'tags' => ['fuel', 'tank']],
        'machinery' => ['category' => 'assets', 'shape' => 'machinery', 'label' => 'Machinery', 'tags' => ['machinery', 'equipment']],
        'portable_asset' => ['category' => 'assets', 'shape' => 'portable_asset', 'label' => 'Portable Asset', 'tags' => ['portable', 'asset']],
        'livestock' => ['category' => 'assets', 'shape' => 'livestock', 'label' => 'Livestock', 'tags' => ['livestock', 'animal']],

        // Special / tracking
        'person' => ['category' => 'special', 'shape' => 'person', 'label' => 'Person', 'tags' => ['person', 'driver']],
        'pet' => ['category' => 'special', 'shape' => 'pet', 'label' => 'Pet', 'tags' => ['pet', 'animal']],
        'gps_tracker' => ['category' => 'special', 'shape' => 'gps_tracker', 'label' => 'GPS Tracker', 'tags' => ['gps', 'tracker', 'device']],
        'mobile_phone' => ['category' => 'special', 'shape' => 'mobile_phone', 'label' => 'Mobile Phone', 'tags' => ['mobile', 'phone']],
        'backpack' => ['category' => 'special', 'shape' => 'backpack', 'label' => 'Backpack', 'tags' => ['backpack', 'bag']],
        'briefcase' => ['category' => 'special', 'shape' => 'briefcase', 'label' => 'Briefcase', 'tags' => ['briefcase', 'bag']],

        // Generic markers
        'default_vehicle' => ['category' => 'generic', 'shape' => 'default_vehicle', 'label' => 'Default Vehicle', 'tags' => ['default', 'generic']],
        'circle_marker' => ['category' => 'generic', 'shape' => 'circle', 'label' => 'Circle Marker', 'tags' => ['circle', 'marker']],
        'pin_marker' => ['category' => 'generic', 'shape' => 'pin', 'label' => 'Pin Marker', 'tags' => ['pin', 'marker']],
        'square_marker' => ['category' => 'generic', 'shape' => 'square', 'label' => 'Square Marker', 'tags' => ['square', 'marker']],
        'star_marker' => ['category' => 'generic', 'shape' => 'star', 'label' => 'Star Marker', 'tags' => ['star', 'marker']],
    ],
];
