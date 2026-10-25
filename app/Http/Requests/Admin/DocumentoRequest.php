<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TipoDocumento;
use App\Support\Contenido\EnlaceIncrustable;
use App\Support\Contenido\Enlaces;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de un documento descargable.
 *
 * No se sube ningún archivo: `archivo_url` es la ruta o la dirección de un PDF que ya está
 * colocado en el frontend o en un CDN. Ver el modelo `Documento`.
 */
final class DocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $obligatorio = $this->esAlta() ? ['required'] : ['sometimes', 'required'];

        return [
            'slug' => [
                ...$obligatorio,
                'string',
                'max:140',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('documentos', 'slug')->ignore($this->route('documento')),
            ],

            'titulo' => [...$obligatorio, 'string', 'max:191'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'tipo' => [...$obligatorio, Rule::enum(TipoDocumento::class)],

            'archivo_url' => [
                ...$obligatorio,
                'string',
                'max:500',
                function (string $atributo, mixed $valor, Closure $fallar): void {
                    $motivo = is_string($valor) ? Enlaces::problemaDeArchivoPdf($valor) : null;

                    if ($motivo !== null) {
                        $fallar("La dirección del PDF {$motivo}");
                    }
                },
            ],

            'tamano_kb' => ['nullable', 'integer', 'min:1', 'max:4000000'],
            'licencia' => ['nullable', 'string', 'max:191'],
            'video_url' => ['nullable', 'string', 'max:2000', EnlaceIncrustable::regla()],
            'fuente_id' => ['nullable', 'integer', 'exists:fuentes,id'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:65535'],

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
            'tipo.enum' => 'Ese tipo de documento no existe.',
            'slug.regex' => 'La dirección solo admite minúsculas, números y guiones: «guia-de-lectura».',
            'slug.unique' => 'Ya hay otro documento con esa dirección.',
            'tamano_kb.min' => 'El tamaño debe ser mayor que cero.',
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
            'slug' => 'la dirección del documento',
            'titulo' => 'el título',
            'descripcion' => 'la descripción',
            'tipo' => 'el tipo',
            'archivo_url' => 'la dirección del PDF',
            'tamano_kb' => 'el tamaño',
            'licencia' => 'la licencia',
            'video_url' => 'la dirección del video',
            'fuente_id' => 'la fuente',
            'orden' => 'el orden',
        ];
    }

    private function esAlta(): bool
    {
        return $this->route('documento') === null;
    }
}
