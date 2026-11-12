<?php

namespace App\Support;

use Illuminate\Http\Request;

class PostedIdList
{
    /**
     * Turn one JSON id list into an array before validation.
     * An id array already posted by checkboxes is left as-is.
     */
    public static function merge(Request $request, string $arrayKey, string $jsonKey): void
    {
        $current = $request->input($arrayKey);
        if (is_array($current)) {
            return;
        }

        $decoded = json_decode((string) $request->input($jsonKey, ''), true);
        $ids = [];
        if (is_array($decoded)) {
            foreach ($decoded as $id) {
                if (is_int($id) && $id > 0) {
                    $ids[] = $id;
                } elseif (is_string($id) && preg_match('/^[1-9]\d*$/', $id) === 1) {
                    $ids[] = (int) $id;
                }
            }
        }

        $request->merge([
            $arrayKey => array_values(array_unique($ids)),
        ]);
    }
}
