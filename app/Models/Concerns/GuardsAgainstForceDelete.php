<?php

namespace App\Models\Concerns;

/**
 * 国家/项目/团队/专业/细分类/版本这条链上的外键都设置了 cascadeOnDelete()，但这些模型
 * 走的是软删除——只要没人调用 forceDelete()，这个数据库级联就是"潜伏"的，不会被触发。
 * 万一以后有人加了"清空回收站"之类的功能、或者在 tinker 里手滑调用了 forceDelete()，
 * 数据库会在 SQL 层面直接级联硬删所有子记录（包括 VersionDrawing/VersionFile），
 * 完全绕过 VersionController::purgeFiles() 那一层 COS 文件清理，导致云存储上的文件
 * 永久孤儿、连数据库记录都不剩，没法追溯、也没法清理。
 *
 * 这里直接拦截 forceDelete()，逼着任何真要硬删的人先想清楚 COS 那边怎么办，而不是
 * 让级联静默生效。
 */
trait GuardsAgainstForceDelete
{
    public function forceDelete(): bool
    {
        throw new \RuntimeException(sprintf(
            '%s 不允许直接 forceDelete()：这条数据链上的外键设置了级联删除，硬删会在数据库'.
            '层面级联删掉所有子记录，绕过 COS 文件清理，导致云存储文件永久孤儿。如果确实'.
            '需要彻底清除数据，请先手动核实并清理相关的 COS 文件。',
            static::class
        ));
    }
}
