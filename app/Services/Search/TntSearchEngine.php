<?php

namespace App\Services\Search;

use App\Models\SearchKey;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Laravel\Scout\Builder;
use RuntimeException;
use TeamTNT\Scout\Engines\TNTSearchEngine as BaseTntSearchEngine;
use TeamTNT\Scout\TNTSearchScoutServiceProvider;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\TNTSearch;

/**
 * TNTSearch 引擎的应用适配层。
 *
 * TNTSearch 使用整数文档 ID，应用使用 ULID。search_keys 保存两种 ID 的固定映射。
 */
class TntSearchEngine extends BaseTntSearchEngine
{
    /**
     * 更新模型的全文索引。
     */
    public function update($models): void
    {
        /** @var Model $first */
        $first = $models->first();

        $this->withWriteLock($first, function () use ($models, $first): void {
            $this->resetTnt();

            $this->initIndex($first);
            $this->getTNT()->selectIndex("{$first->searchableAs()}.index");
            $index = $this->getTNT()->getIndex();
            $index->setPrimaryKey($first->getKeyName());

            $models->each(function (Model $model) use ($index): void {
                if (method_exists($model, 'shouldBeSearchable') && ! $model->shouldBeSearchable()) {
                    return;
                }

                $document = $model->toSearchableArray();
                if ($document === []) {
                    return;
                }

                $searchKey = $this->searchKeyFor($model);
                $document['id'] = $searchKey;
                $index->update($searchKey, $document);
            });
        });
    }

    /**
     * 从全文索引删除模型。
     */
    public function delete($models): void
    {
        /** @var Model $first */
        $first = $models->first();

        $this->withWriteLock($first, function () use ($models, $first): void {
            $this->resetTnt();

            $this->initIndex($first);

            $models->each(function (Model $model): void {
                $searchKey = SearchKey::query()
                    ->where('model_type', $model::class)
                    ->where('model_id', (string) $model->getKey())
                    ->value('id');

                if ($searchKey === null) {
                    return;
                }

                $this->getTNT()->selectIndex("{$model->searchableAs()}.index");
                $index = $this->getTNT()->getIndex();
                $index->setPrimaryKey($model->getKeyName());
                $index->delete((int) $searchKey);

                SearchKey::query()->whereKey($searchKey)->delete();
            });
        });
    }

    /**
     * 执行全文检索。
     */
    public function search(Builder $builder)
    {
        $this->resetTnt();

        try {
            return $this->performSearch($builder);
        } catch (IndexNotFoundException) {
            $this->withWriteLock($builder->model, function () use ($builder): void {
                $this->initIndex($builder->model);
            });

            return $this->emptySearchResults();
        }
    }

    /**
     * 把 TNTSearch 返回的整数 ID 转换成模型 ID。
     */
    public function mapIds($results)
    {
        if (empty($results['ids'])) {
            return collect();
        }

        return collect($this->modelIdsForSearchKeys(
            $this->builder->model,
            $results['ids'],
        ));
    }

    /**
     * 把全文结果映射为模型。
     */
    public function map(Builder $builder, $results, $model)
    {
        return parent::map($builder, $this->convertResultsToModelIds($results, $model), $model);
    }

    /**
     * 把延迟全文结果映射为模型。
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        return parent::lazyMap($builder, $this->convertResultsToModelIds($results, $model), $model);
    }

    /**
     * 按 Scout 查询约束过滤整数文档 ID，并保留 TNTSearch 排序。
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $this->resetTnt();
        try {
            $results = $this->performSearch($builder);
        } catch (IndexNotFoundException) {
            $this->withWriteLock($builder->model, function () use ($builder): void {
                $this->initIndex($builder->model);
            });

            return $this->emptySearchResults();
        }

        if ($builder->limit) {
            $results['hits'] = $builder->limit;
        }

        $filtered = $this->filterSearchKeysByBuilder($builder, $results['ids']);
        $results['hits'] = $filtered->count();

        $chunks = array_chunk($filtered->all(), $perPage);
        if ($chunks === []) {
            return $results;
        }

        $results['ids'] = $chunks[$page - 1] ?? [];

        return $results;
    }

    /**
     * 将 Scout 的 where in 条件应用到模型查询。
     */
    public function getBuilder($model)
    {
        $builder = parent::getBuilder($model);

        foreach ($this->builder->whereIns as $field => $values) {
            $builder->whereIn($field, $values);
        }

        foreach ($this->builder->whereNotIns as $field => $values) {
            $builder->whereNotIn($field, $values);
        }

        return $builder;
    }

    /**
     * 删除模型对应的全文索引和 ID 映射。
     */
    public function flush($model): void
    {
        $this->withWriteLock($model, function () use ($model): void {
            parent::flush($model);

            SearchKey::query()->where('model_type', $model::class)->delete();
        });
    }

    /**
     * 转换原始搜索结果中的文档 ID 和分数键。
     *
     * @param  array<string, mixed>  $results
     * @return array<string, mixed>
     */
    private function convertResultsToModelIds(array $results, Model $model): array
    {
        $searchKeys = $results['ids'] ?? [];
        $mapping = $this->searchKeyMap($model, $searchKeys);

        $results['ids'] = array_values(array_map(
            fn (int|string $searchKey): string => $mapping[(int) $searchKey],
            $searchKeys,
        ));

        if (isset($results['docScores'])) {
            $scores = [];
            foreach ($searchKeys as $searchKey) {
                $modelId = $mapping[(int) $searchKey];
                $scores[$modelId] = (float) ($results['docScores'][$searchKey] ?? 0.0);
            }
            $results['docScores'] = $scores;
        }

        return $results;
    }

    /**
     * 返回搜索键到模型 ID 的映射。
     *
     * @param  list<int|string>  $searchKeys
     * @return array<int, string>
     */
    private function searchKeyMap(Model $model, array $searchKeys): array
    {
        return SearchKey::query()
            ->where('model_type', $model::class)
            ->whereIn('id', $searchKeys)
            ->pluck('model_id', 'id')
            ->map(static fn ($modelId): string => (string) $modelId)
            ->all();
    }

    /**
     * 返回模型 ID 列表。
     *
     * @param  list<int|string>  $searchKeys
     * @return list<string>
     */
    private function modelIdsForSearchKeys(Model $model, array $searchKeys): array
    {
        $mapping = $this->searchKeyMap($model, $searchKeys);

        return array_values(array_map(
            fn (int|string $searchKey): string => $mapping[(int) $searchKey],
            $searchKeys,
        ));
    }

    /**
     * 按模型查询约束保留 TNTSearch 文档 ID。
     *
     * @param  list<int|string>  $searchKeys
     * @return Collection<int, int|string>
     */
    private function filterSearchKeysByBuilder(Builder $builder, array $searchKeys)
    {
        $model = $builder->model;
        $mapping = $this->searchKeyMap($model, $searchKeys);
        $modelIds = array_values($mapping);

        $allowedModelIds = $this->getBuilder($model)
            ->whereIn($model->getQualifiedKeyName(), $modelIds)
            ->pluck($model->getKeyName())
            ->map(static fn ($modelId): string => (string) $modelId)
            ->flip();

        return collect($searchKeys)
            ->filter(fn (int|string $searchKey): bool => isset($allowedModelIds[$mapping[(int) $searchKey]]))
            ->values();
    }

    /**
     * 返回模型对应的整数搜索键。
     */
    private function searchKeyFor(Model $model): int
    {
        SearchKey::query()->upsert([
            [
                'model_type' => $model::class,
                'model_id' => (string) $model->getKey(),
            ],
        ], ['model_type', 'model_id'], ['model_id']);

        return (int) SearchKey::query()
            ->where('model_type', $model::class)
            ->where('model_id', (string) $model->getKey())
            ->value('id');
    }

    /**
     * 为每次索引操作创建独立的 TNTSearch 实例。
     */
    private function resetTnt(): void
    {
        $tnt = new TNTSearch;
        $driver = config('database.default');
        $config = config('scout.tntsearch') + config("database.connections.{$driver}");

        $tnt->loadConfig($config);
        $tnt->setDatabaseHandle(app('db')->connection()->getReadPdo());
        $tnt->engine->maxDocs = config('scout.tntsearch.maxDocs', 500);
        TNTSearchScoutServiceProvider::setFuzziness($tnt);
        TNTSearchScoutServiceProvider::setAsYouType($tnt);

        $this->tnt = $tnt;
    }

    /**
     * 返回空索引的搜索结果。
     *
     * @return array{ids: list<int>, hits: int, docScores: array<int, float>}
     */
    private function emptySearchResults(): array
    {
        return [
            'ids' => [],
            'hits' => 0,
            'docScores' => [],
        ];
    }

    /**
     * 为同一模型的 TNTSearch 写操作建立文件锁。
     */
    private function withWriteLock(Model $model, Closure $operation): mixed
    {
        $storage = (string) config('scout.tntsearch.storage');
        File::ensureDirectoryExists($storage);

        $lockPath = rtrim($storage, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR
            .$model->searchableAs().'.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new RuntimeException("Unable to open TNTSearch lock [{$lockPath}].");
        }

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException("Unable to lock TNTSearch index [{$model->searchableAs()}].");
        }

        try {
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
