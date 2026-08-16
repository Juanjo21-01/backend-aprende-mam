<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de una entrada del diccionario.
 *
 * Dos cosas que este archivo **no** hace, y que no son olvidos:
 *
 * 1. No normaliza. Eso es del mutator del modelo, para que también cubra los seeders, el
 *    importador del corpus y tinker. Aquí solo se comprueba la forma.
 * 2. No rechaza `õ` ni apóstrofos rectos. El Manual de Normas le promete al editor que el
 *    panel corrige eso solo al guardar —«es el sistema trabajando bien, no un error»—, así
 *    que rechazarlos contradiría el manual que se entrega con la plataforma.
 *
 * Lo que sí rechaza, y en voz alta, es que alguien mande las columnas derivadas o la
 * bandera de revisión: `prohibited` falla en lugar de ignorarlas en silencio.
 */
final class EntradaRequest extends FormRequest
{
    /** La autorización va por política, enganchada con `#[Authorize]` en el controlador. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // En una edición parcial basta con mandar lo que cambió; lo que se manda, eso sí,
        // no puede venir vacío.
        $obligatorio = $this->esAlta() ? ['required'] : ['sometimes', 'required'];

        return [
            'mam' => [...$obligatorio, 'string', 'max:191'],
            'espanol' => [...$obligatorio, 'string'],
            'definicion' => ['nullable', 'string'],
            'categoria_gramatical_id' => ['nullable', 'integer', 'exists:categorias_gramaticales,id'],
            'municipio' => ['nullable', 'string', 'max:100'],
            'fuente_id' => ['nullable', 'integer', 'exists:fuentes,id'],
            'pagina_fuente' => ['nullable', 'string', 'max:20'],

            // Opcional a propósito: el Manual de Normas dice que una palabra se puede
            // guardar incompleta y terminarse después. Tener tema es requisito de la lista
            // de verificación previa a publicar, no del guardado.
            'categorias' => ['nullable', 'array'],
            'categorias.*' => ['integer', 'exists:categorias,id'],

            'busqueda' => ['prohibited'],
            'orden_alfabetico' => ['prohibited'],
            'revisado' => ['prohibited'],
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
            'integer' => ':attribute debe ser un número.',
            'array' => 'Los temas deben venir como lista.',
            'max' => ':attribute es demasiado largo.',
            'exists' => ':attribute no existe.',

            'busqueda.prohibited' => 'La clave de búsqueda la calcula el sistema; no se acepta desde el formulario.',
            'orden_alfabetico.prohibited' => 'El orden alfabético lo calcula el sistema; no se acepta desde el formulario.',
            'revisado.prohibited' => 'La revisión se marca desde su propia acción y solo la puede hacer el validador lingüístico.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mam' => 'la palabra en Mam',
            'espanol' => 'la traducción al castellano',
            'definicion' => 'la definición',
            'categoria_gramatical_id' => 'la clase de palabra',
            'municipio' => 'el municipio',
            'fuente_id' => 'la fuente',
            'pagina_fuente' => 'la página de la fuente',
            'categorias' => 'los temas',
            'categorias.*' => 'el tema',
        ];
    }

    private function esAlta(): bool
    {
        return $this->route('entrada') === null;
    }
}
