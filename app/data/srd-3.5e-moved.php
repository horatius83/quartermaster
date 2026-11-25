<?php
// Open Game Content from the System Reference Document, used under the Open Game License 1.0a: the whole of this
// file is Open Game Content, and the license and copyright notice are in public/assets/srd-notices.txt.
// Rows that earlier versions of this system's data had in another set, by the set they're in now. The SRD prices these
// nonmagical items in its magic item tables, so they used to load as magic items; loading the gear now re-files the
// ones a catalog still has that way (see refile_srd_items()). Each is name => [category slug, tags, description] as
// the old row had them. An item is only taken for the old row's while it's in that category with all of those tags;
// then its tags are swapped for the new row's, and a description still the old one is replaced. Every name here must
// also be in the set it's listed under (tests/e2e/srd-data-check.php checks).
declare(strict_types=1);

return [
    'gear' => [
        'Adamantine battleaxe' => ['magic', ['medium', 'minor', 'weapon'], 'This nonmagical axe is made out of adamantine. As a masterwork weapon, it has a +1 enhancement bonus on attack rolls. No aura (nonmagical).'],
        'Adamantine breastplate' => ['magic', ['armor', 'major', 'medium'], 'This nonmagical breastplate is made of adamantine, giving its wearer damage reduction of 2/-. No aura (nonmagical).'],
        'Adamantine dagger' => ['magic', ['medium', 'minor', 'weapon'], 'This nonmagical dagger is made out of adamantine. As a masterwork weapon, it has a +1 enhancement bonus on attack rolls. No aura (nonmagical).'],
        'Cold iron longsword, masterwork' => ['magic', ['minor', 'weapon'], 'This nonmagical longsword is crafted out of cold iron. As a masterwork weapon, it has a +1 enhancement bonus on attack rolls. No aura (nonmagical).'],
        'Darkwood buckler' => ['magic', ['medium', 'minor', 'shield'], 'This nonmagical light wooden shield is made out of darkwood. It has no enhancement bonus, but its construction material makes it lighter than a normal wooden shield. It weighs 2-1/2 pounds and has no armor check penalty. No aura (nonmagical).'],
        'Darkwood shield' => ['magic', ['medium', 'minor', 'shield'], 'This nonmagical heavy wooden shield is made out of darkwood. It has no enhancement bonus, but its construction material makes it lighter than a normal wooden shield. It weighs 5 pounds and has no armor check penalty. No aura (nonmagical).'],
        'Dragonhide plate' => ['magic', ['armor', 'medium', 'minor'], 'This suit of full plate is made of dragonhide, rather than metal, so druids can wear it. It is otherwise identical to masterwork full plate. No aura (nonmagical).'],
        'Dwarven plate' => ['magic', ['armor', 'major', 'medium'], 'This full plate is made of adamantine, giving its wearer damage reduction of 3/-. No aura (nonmagical).'],
        'Elven chain' => ['magic', ['armor', 'medium', 'minor'], 'This extremely light chainmail is made of very fine mithral links. Speed while wearing elven chain is 30 feet for Medium creatures, or 20 feet for Small. The armor has an arcane spell failure chance of 20%, a maximum Dexterity bonus of +4, and an armor check penalty of -2. It is considered light armor and weighs 20 pounds. No aura (nonmagical).'],
        'Mithral heavy shield' => ['magic', ['medium', 'minor', 'shield'], 'This heavy shield is made of mithral and thus is much lighter than a standard steel shield. It has a 5% arcane spell failure chance and no armor check penalty. It weighs 5 pounds. No aura (nonmagical).'],
        'Mithral shirt' => ['magic', ['armor', 'medium', 'minor'], 'This extremely light chain shirt is made of very fine mithral links. Speed while wearing a mithral shirt is 30 feet for Medium creatures, or 20 feet for Small. The armor has an arcane spell failure chance of 10%, a maximum Dexterity bonus of +6, and no armor check penalty. It is considered light armor and weighs 10 pounds. No aura (nonmagical).'],
        'Silver dagger, masterwork' => ['magic', ['minor', 'weapon'], 'This masterwork alchemical silver dagger is nonmagical. As a masterwork weapon, it has a +1 enhancement bonus on attack rolls. No aura (nonmagical).'],
    ],
];
