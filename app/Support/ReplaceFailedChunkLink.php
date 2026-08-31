<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class ReplaceFailedChunkLink
{
    /**
     * Overwrite url/keyword on one failed slot. Does not post; Retry failed still sends it.
     */
    public static function update(Model $chunk, int $index, string $url, string $keyword): void
    {
        $status = (string) ($chunk->status ?? '');
        if ($status === 'processing') {
            throw new \RuntimeException('This chunk is still publishing. Wait until it finishes, then replace.');
        }
        if ($status === 'pending') {
            throw new \RuntimeException('This link has not been posted yet.');
        }

        $links = is_array($chunk->links_payload) ? $chunk->links_payload : [];
        if (! array_key_exists($index, $links) || ! is_array($links[$index])) {
            throw new \RuntimeException('Link not found in this chunk.');
        }

        if (! method_exists($chunk, 'failedLinkIndices') || ! in_array($index, $chunk->failedLinkIndices(), true)) {
            throw new \RuntimeException('Only failed links can be replaced.');
        }

        $links[$index]['url'] = $url;
        $links[$index]['keyword'] = $keyword;
        $chunk->update(['links_payload' => $links]);
    }
}
