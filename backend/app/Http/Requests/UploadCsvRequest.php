<?php

namespace App\Http\Requests;

use App\Services\CsvParser;
use Illuminate\Foundation\Http\FormRequest;

class UploadCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Single-user portfolio app; add auth before exposing publicly.
    }

    public function rules(): array
    {
        return [
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:' . (CsvParser::MAX_FILE_SIZE_BYTES / 1024), // KB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Attach an AWS billing CSV file.',
            'csv_file.mimes' => 'Only .csv files are accepted.',
            'csv_file.max' => 'The CSV file must be 10 MB or smaller.',
        ];
    }
}
