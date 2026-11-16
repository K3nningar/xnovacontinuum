<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SimulateCombatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $unitCodes = implode(',', array_keys(config('combat.units')));
        $shipCodes = collect(config('combat.units'))
            ->filter(fn (array $unit): bool => $unit['type'] === 'ships')
            ->keys()->implode(',');

        $rules = [
            'attacker' => ['required', 'array:fleet,technologies'],
            'attacker.fleet' => ['required', 'array:'.$shipCodes],
            'attacker.fleet.*' => ['required', 'integer', 'between:0,'.config('combat.max_units_per_side')],
            'attacker.technologies' => ['required', 'array:weapons,shields,armour'],
            'attacker.technologies.weapons' => ['required', 'integer', 'between:0,100'],
            'attacker.technologies.shields' => ['required', 'integer', 'between:0,100'],
            'attacker.technologies.armour' => ['required', 'integer', 'between:0,100'],
            'defender' => ['required', 'array:fleet,technologies'],
            'defender.fleet' => ['required', 'array:'.$unitCodes],
            'defender.fleet.*' => ['required', 'integer', 'between:0,'.config('combat.max_units_per_side')],
            'defender.technologies' => ['required', 'array:weapons,shields,armour'],
            'defender.technologies.weapons' => ['required', 'integer', 'between:0,100'],
            'defender.technologies.shields' => ['required', 'integer', 'between:0,100'],
            'defender.technologies.armour' => ['required', 'integer', 'between:0,100'],
            'seed' => ['nullable', 'integer', 'between:0,2147483647'],
        ];

        $specialCodes = collect(config('combat.units'))
            ->filter(fn (array $unit): bool => $unit['custom_profile'] ?? false)
            ->keys();
        $rules['special_profiles'] = ['sometimes', 'array:'.$specialCodes->implode(',')];

        foreach ($specialCodes as $code) {
            $attackerCount = $this->input("attacker.fleet.$code");
            $defenderCount = $this->input("defender.fleet.$code");
            $used = (is_numeric($attackerCount) && $attackerCount > 0)
                || (is_numeric($defenderCount) && $defenderCount > 0);
            $rules["special_profiles.$code"] = ['sometimes', 'array:attack,shield,hull'];
            foreach (['attack', 'shield', 'hull'] as $stat) {
                $minimum = $stat === 'hull' ? 1 : 0;
                $rules["special_profiles.$code.$stat"] = [$used ? 'required' : 'nullable', 'integer', "between:$minimum,1000000000"];
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['attacker', 'defender'] as $side) {
                    $hasFleetErrors = collect($validator->errors()->keys())
                        ->contains(fn (string $key): bool => $key === "$side.fleet" || str_starts_with($key, "$side.fleet."));

                    if ($hasFleetErrors) {
                        continue;
                    }

                    $fleet = $this->input("$side.fleet", []);
                    $total = array_sum(array_map('intval', $fleet));

                    if ($total === 0) {
                        $validator->errors()->add("$side.fleet", 'Chaque camp doit engager au moins une unité.');
                    } elseif ($total > config('combat.max_units_per_side')) {
                        $validator->errors()->add(
                            "$side.fleet",
                            'Chaque camp peut engager au maximum '.config('combat.max_units_per_side').' unités.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attacker.fleet.required' => 'Définissez la flotte de l’attaquant.',
            'attacker.fleet.array' => 'La flotte de l’attaquant contient une unité non prise en charge.',
            'attacker.fleet.*.integer' => 'Les effectifs de l’attaquant doivent être des nombres entiers.',
            'attacker.fleet.*.between' => 'Les effectifs de l’attaquant doivent être compris entre 0 et '.config('combat.max_units_per_side').'.',
            'defender.fleet.required' => 'Définissez la flotte du défenseur.',
            'defender.fleet.array' => 'La flotte du défenseur contient une unité non prise en charge.',
            'defender.fleet.*.integer' => 'Les effectifs du défenseur doivent être des nombres entiers.',
            'defender.fleet.*.between' => 'Les effectifs du défenseur doivent être compris entre 0 et '.config('combat.max_units_per_side').'.',
            'seed.integer' => 'La graine doit être un nombre entier.',
            'seed.between' => 'La graine doit être comprise entre 0 et 2 147 483 647.',
        ];
    }
}
