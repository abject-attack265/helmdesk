<?php

use App\Exceptions\BusinessException;
use App\Models\AiModel;
use App\Services\KnowledgeBase\KnowledgeEmbeddingProviderFactory;
use App\Services\KnowledgeBase\KnowledgeEmbeddingService;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;

/**
 * 返回确定性向量的假 embeddings provider。
 *
 * @param  list<float>|callable(string): list<float>  $vector
 */
function fakeEmbeddingsProvider(array|callable $vector): EmbeddingsProviderInterface
{
    return new class($vector) implements EmbeddingsProviderInterface
    {
        /** @param list<float>|callable(string): list<float> $vector */
        public function __construct(private $vector) {}

        public function embedText(string $text): array
        {
            return is_callable($this->vector) ? ($this->vector)($text) : $this->vector;
        }

        public function embedDocument(Document $document): Document
        {
            return $document;
        }

        public function embedDocuments(array $documents): array
        {
            return $documents;
        }
    };
}

/**
 * 用假 provider 构造 embedding 服务。
 */
function embeddingServiceWith(EmbeddingsProviderInterface $provider): KnowledgeEmbeddingService
{
    $factory = Mockery::mock(KnowledgeEmbeddingProviderFactory::class);
    $factory->shouldReceive('make')->andReturn($provider);

    return new KnowledgeEmbeddingService($factory);
}

test('embedTexts 返回维度与归一化向量列表', function () {
    // 逐条嵌入返回维度与向量
    $service = embeddingServiceWith(fakeEmbeddingsProvider([0.1, 0.2, 0.3]));
    [$dimension, $vectors] = $service->embedTexts(new AiModel, ['你好', '在吗', '帮我下单']);
    expect($dimension)->toBe(3)
        ->and($vectors)->toHaveCount(3)
        ->and($vectors[0])->toBe([0.1, 0.2, 0.3]);

    // 空内容返回零维度空向量
    $emptyService = embeddingServiceWith(fakeEmbeddingsProvider([0.1]));
    expect($emptyService->embedTexts(new AiModel, []))->toBe([0, []]);

    // 整数向量被规范化为 float
    $intService = embeddingServiceWith(fakeEmbeddingsProvider([1, 2, 3]));
    [, $intVectors] = $intService->embedTexts(new AiModel, ['x']);
    expect($intVectors[0])->toBe([1.0, 2.0, 3.0]);
});

test('维度不一致时抛 BusinessException', function () {
    // 第一条返回 3 维，第二条返回 2 维
    $service = embeddingServiceWith(fakeEmbeddingsProvider(
        fn (string $text): array => $text === 'a' ? [1.0, 2.0, 3.0] : [1.0, 2.0],
    ));

    $service->embedTexts(new AiModel, ['a', 'b']);
})->throws(BusinessException::class);
