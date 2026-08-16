<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de una fuente bibliográfica.
 *
 * Sin normalización ortográfica: el título es una **cita** y tiene que reproducir la
 * portada tal como está impresa. El razonamiento completo está en el modelo `Fuente`.
 */
final class FuenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $obligatorio = $this->esAlta() ? ['required'] : ['sometimes', 'required'];

        return [
            'titulo' => [...$obligatorio, 'string', 'max:255'],
            'institucion' => ['nullable', 'string', 'max:191'],

            // Las fuentes del corpus van de 2011 a 2018; el rango es holgado por si se
            // incorpora material más antiguo de ALMG.
            'anio' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],

            'licencia' => ['nullable', 'string', 'max:191'],
            'url' => ['nullable', 'url', 'max:500'],
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
            'anio.integer' => 'El año debe ser un número.',
            'anio.min' => 'El año parece demasiado antiguo.',
            'anio.max' => 'El año no puede estar en el futuro.',
            'url.url' => 'La dirección web no tiene un formato válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'el título',
            'institucion' => 'la institución',
            'anio' => 'el año',
            'licencia' => 'la licencia',
            'url' => 'la dirección web',
        ];
    }

    private function esAlta(): bool
    {
        return $this->route('fuente') === null;
    }
}
