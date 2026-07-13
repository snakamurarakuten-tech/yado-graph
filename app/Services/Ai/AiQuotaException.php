<?php
declare(strict_types=1);

namespace App\Services\Ai;

/**
 * APIクォータ超過(HTTP 429)の専用例外。
 * 「公式HPが見つからない」と区別するために投げる。
 * バッチはこれを受けたら state を汚さずに停止する(翌日同じ宿から再開できる)。
 */
final class AiQuotaException extends \RuntimeException
{
}
