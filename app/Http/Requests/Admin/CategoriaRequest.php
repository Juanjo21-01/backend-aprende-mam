<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un tema del vocabulario.
 *
 * `nombre_mam` no se valida contra la ortografía por el mismo motivo que en
 * `EntradaRequest`: lo sanea el mutator del modelo al guardar.
 */
final class CategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $obligatorio = $this->esAlta() ? ['required'] : ['sometimes', 'required'];
        $categoria = $this->categoriaEnEdicion();

        return [
            'nombre_es' => [...$obligatorio, 'string', 'max:120'],
            'nombre_mam' => ['nullable', 'string', 'max:120'],

            'slug' => [
                ...$obligatorio,
                'string',
                'max:140',
                // El slug va en la URL del sitio estático: solo lo que sobrevive ahí.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categorias', 'slug')->ignore($categoria),
            ],

            'icono' => ['nullable', 'string', 'max:60'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:65535'],

            'padre_id' => [
                'nullable',
                'integer',
                'exists:categorias,id',
                // Un tema no puede ser su propio padre. Los ciclos más largos (A→B→A) no se
                // comprueban: la taxonomía del módulo L2 tiene dos niveles y añadir un
                // recorrido del árbol en cada guardado costaría más de lo que evita.
                Rule::notIn(array_filter([$categoria?->id])),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Falta :attribute.',
            'string' => ':attribute debe ser texto.',
            'max' => ':attribute es demasiado largo.',
            'slug.regex' => 'La dirección solo admite minúsculas, números y guiones: «medicina-natural».',
            'slug.unique' => 'Ya hay otro tema con esa dirección.',
            'padre_id.exists' => 'El tema padre no existe.',
            'padre_id.not_in' => 'Un tema no puede ser su propio padre.',
            'orden.integer' => 'El orden debe ser un número.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre_es' => 'el nombre en castellano',
            'nombre_mam' => 'el nombre en Mam',
            'slug' => 'la dirección del tema',
            'icono' => 'el ícono',
            'orden' => 'el orden',
            'padre_id' => 'el tema padre',
        ];
    }

    private function categoriaEnEdicion(): ?Categoria
    {
        $categoria = $this->route('categoria');

        return $categoria instanceof Categoria ? $categoria : null;
    }

    private function esAlta(): bool
    {
        return $this->route('categoria') === null;
    }
}
