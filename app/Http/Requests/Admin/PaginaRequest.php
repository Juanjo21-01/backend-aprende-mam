<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SeccionPagina;
use App\Models\Pagina;
use App\Support\Contenido\EnlaceIncrustable;
use App\Support\Contenido\MarkdownSimple;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una página editorial.
 *
 * Como `EntradaRequest`, **no normaliza**: eso es del mutator del modelo. Aquí se comprueba
 * la forma, y además dos cosas que el modelo no puede decidir porque no son ortografía:
 * que el cuerpo sea Markdown simple y que el video sea de un proveedor que el frontend
 * sabe incrustar.
 *
 * `revisado` es `prohibited`: se marca desde su propia ruta y solo la usa el validador.
 */
final class PaginaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mover una página de sección sin tocar el slug también tiene que comprobar que el slug
     * esté libre en la sección nueva. Con `sometimes`, un request que no menciona el slug
     * se salta su regla de unicidad y el choque aparecería como un 500 de la base. Se
     * completa con el slug actual para que la regla corra.
     */
    protected function prepareForValidation(): void
    {
        $pagina = $this->route('pagina');

        if ($pagina instanceof Pagina && $this->has('seccion') && ! $this->has('slug')) {
            $this->merge(['slug' => $pagina->slug]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $obligatorio = $this->esAlta() ? ['required'] : ['sometimes', 'required'];

        return [
            'seccion' => [...$obligatorio, Rule::enum(SeccionPagina::class)],

            'slug' => [
                ...$obligatorio,
                'string',
                'max:140',
                // Va en la URL del sitio estático: solo lo que sobrevive ahí.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                // Único **dentro de la sección**: «historia» puede estar en dos.
                Rule::unique('paginas', 'slug')
                    ->where('seccion', $this->seccionResultante())
                    ->ignore($this->route('pagina')),
            ],

            'titulo' => [...$obligatorio, 'string', 'max:191'],

            'cuerpo' => [
                ...$obligatorio,
                'string',
                'max:200000',
                function (string $atributo, mixed $valor, Closure $fallar): void {
                    $motivo = is_string($valor) ? MarkdownSimple::problema($valor) : null;

                    if ($motivo !== null) {
                        $fallar("El cuerpo {$motivo}");
                    }
                },
            ],

            'orden' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'video_url' => ['nullable', 'string', 'max:2000', EnlaceIncrustable::regla()],
            'fuente_id' => ['nullable', 'integer', 'exists:fuentes,id'],

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
            'max' => ':attribute es demasiado largo.',
            'exists' => ':attribute no existe.',
            'seccion.enum' => 'Esa sección no existe.',
            'slug.regex' => 'La dirección solo admite minúsculas, números y guiones: «historia-del-mam».',
            'slug.unique' => 'Ya hay otra página con esa dirección en esa sección.',
            'orden.min' => 'El orden no puede ser negativo.',
            'revisado.prohibited' => 'La revisión se marca desde su propia acción y solo la puede hacer el validador lingüístico.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'seccion' => 'la sección',
            'slug' => 'la dirección de la página',
            'titulo' => 'el título',
            'cuerpo' => 'el cuerpo',
            'orden' => 'el orden',
            'video_url' => 'la dirección del video',
            'fuente_id' => 'la fuente',
        ];
    }

    /**
     * La sección contra la que se comprueba el slug: la que viene, o la de la página que se
     * edita si no se mandó. Sin esto, mover una página de sección sin tocar el slug
     * chocaría —o no— contra la sección equivocada.
     */
    private function seccionResultante(): ?string
    {
        $enviada = $this->input('seccion');

        if (is_string($enviada)) {
            return $enviada;
        }

        $pagina = $this->route('pagina');

        return $pagina instanceof Pagina ? $pagina->seccion->value : null;
    }

    private function esAlta(): bool
    {
        return $this->route('pagina') === null;
    }
}
