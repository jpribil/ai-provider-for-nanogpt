<?php

declare(strict_types=1);

namespace WordPress\NanoGptAiProvider\Metadata;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\NanoGptAiProvider\Provider\NanoGptProvider;

/**
 * Class for the NanoGPT model metadata directory.
 *
 * @since 0.1.0
 *
 * @phpstan-type ModelData array{
 *     id?: string,
 *     name?: string,
 *     capabilities?: array<string, mixed>,
 *     architecture?: array<string, mixed>,
 *     supported_parameters?: array<string, mixed>
 * }
 * @phpstan-type ModelsResponseData array{
 *     data?: list<ModelData>
 * }
 */
class NanoGptModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory
{
    private const CACHE_VERSION = '0.5.0';

    /**
     * Preferred default image models. The first available compatible model wins.
     *
     * @since 0.1.1
     *
     * @var list<string>
     */
    private const DEFAULT_PREFERRED_IMAGE_MODEL_IDS = [
        'gpt-image-1.5',
        'nano-banana-pro',
        'nano-banana-2',
        'seedream-v4.5',
        'qwen-image-2.0-pro',
        'krea/v2/large/text-to-image',
        'nvidia/cosmos-3-super/text-to-image',
    ];

    /**
     * Preferred default text models. Empty by default to preserve NanoGPT catalog order.
     *
     * @since 0.1.1
     *
     * @var list<string>
     */
    private const DEFAULT_PREFERRED_TEXT_MODEL_IDS = [];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected function sendListModelsRequest(): array
    {
        $models = array_merge(
            $this->fetchModelMetadataList('models?detailed=true', false),
            $this->fetchModelMetadataList('image-models?detailed=true', true)
        );

        usort($models, [$this, 'modelSortCallback']);

        $modelMap = [];
        foreach ($models as $modelMetadata) {
            $modelMap[$modelMetadata->getId()] = $modelMetadata;
        }

        return $modelMap;
    }

    /**
     * Fetches and parses a NanoGPT model list endpoint.
     *
     * @since 0.1.0
     *
     * @param string $path Endpoint path relative to /api/v1.
     * @param bool   $image Whether the endpoint contains image models.
     * @return list<ModelMetadata> Model metadata list.
     */
    protected function fetchModelMetadataList(string $path, bool $image): array
    {
        $httpTransporter = $this->getHttpTransporter();

        $request = new Request(HttpMethodEnum::GET(), NanoGptProvider::url($path));
        $request = $this->getRequestAuthentication()->authenticateRequest($request);
        $response = $httpTransporter->send($request);

        ResponseUtil::throwIfNotSuccessful($response);

        return $this->parseResponseToModelMetadataList($response, $image);
    }

    /**
     * Parses a NanoGPT model list response.
     *
     * @since 0.1.0
     *
     * @param Response $response The model list response.
     * @param bool     $image Whether the response contains image models.
     * @return list<ModelMetadata> Model metadata list.
     */
    protected function parseResponseToModelMetadataList(Response $response, bool $image): array
    {
        /** @var ModelsResponseData $responseData */
        $responseData = $response->getData();
        if (!isset($responseData['data']) || !$responseData['data']) {
            throw ResponseException::fromMissingData('NanoGPT', 'data');
        }
        if (!is_array($responseData['data'])) {
            throw ResponseException::fromInvalidData('NanoGPT', 'data', 'The value must be an array.');
        }

        $models = [];
        foreach ($responseData['data'] as $index => $modelData) {
            if (!is_array($modelData) || !isset($modelData['id']) || !is_string($modelData['id'])) {
                throw ResponseException::fromInvalidData(
                    'NanoGPT',
                    "data[{$index}]",
                    'Each model must include a string id.'
                );
            }

            $modelMetadata = $image
                ? $this->createImageModelMetadata($modelData)
                : $this->createTextModelMetadata($modelData);

            if ($modelMetadata !== null) {
                $models[] = $modelMetadata;
            }
        }

        return $models;
    }

    /**
     * Creates metadata for a NanoGPT text model.
     *
     * @since 0.1.0
     *
     * @param ModelData $modelData Raw model data.
     * @return ModelMetadata Model metadata.
     */
    protected function createTextModelMetadata(array $modelData): ModelMetadata
    {
        $capabilities = [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()];

        $inputModalities = [[ModalityEnum::text()]];
        if (
            isset($modelData['capabilities']) &&
            is_array($modelData['capabilities']) &&
            !empty($modelData['capabilities']['vision'])
        ) {
            $inputModalities[] = [ModalityEnum::text(), ModalityEnum::image()];
        }

        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::presencePenalty()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(OptionEnum::logprobs()),
            new SupportedOption(OptionEnum::topLogprobs()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::functionDeclarations()),
            new SupportedOption(OptionEnum::inputModalities(), $inputModalities),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::customOptions()),
        ];

        return new ModelMetadata(
            $modelData['id'],
            $this->getModelName($modelData),
            $capabilities,
            $options
        );
    }

    /**
     * Creates metadata for a NanoGPT image model.
     *
     * @since 0.1.0
     *
     * @param ModelData $modelData Raw model data.
     * @return ModelMetadata|null Model metadata, or null if the model should not be exposed.
     */
    protected function createImageModelMetadata(array $modelData): ?ModelMetadata
    {
        $inputModalities = [];
        $supportsImageInput = false;
        $supportsTextToImage = true;

        if (isset($modelData['capabilities']) && is_array($modelData['capabilities'])) {
            $supportsTextToImage = !isset($modelData['capabilities']['image_generation']) ||
                !empty($modelData['capabilities']['image_generation']);
            $supportsImageInput = !empty($modelData['capabilities']['image_to_image']) ||
                !empty($modelData['capabilities']['inpainting']);
        }
        if (!$supportsImageInput && isset($modelData['architecture']) && is_array($modelData['architecture'])) {
            $inputModalitiesData = $modelData['architecture']['input_modalities'] ?? null;
            $supportsImageInput = is_array($inputModalitiesData) && in_array('image', $inputModalitiesData, true);
        }
        if ($supportsTextToImage) {
            $inputModalities[] = [ModalityEnum::text()];
        }
        if ($supportsImageInput) {
            $inputModalities[] = [ModalityEnum::text(), ModalityEnum::image()];
        }
        if ($inputModalities === []) {
            return null;
        }

        $options = [
            new SupportedOption(OptionEnum::inputModalities(), $inputModalities),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::image()]]),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::outputMimeType(), ['image/png']),
            new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline(), FileTypeEnum::remote()]),
            new SupportedOption(OptionEnum::customOptions()),
        ];

        return new ModelMetadata(
            $modelData['id'],
            $this->getModelName($modelData),
            [CapabilityEnum::imageGeneration()],
            $options
        );
    }

    /**
     * Gets a display name from raw NanoGPT model data.
     *
     * @since 0.1.0
     *
     * @param ModelData $modelData Raw model data.
     * @return string Display name.
     */
    protected function getModelName(array $modelData): string
    {
        return isset($modelData['name']) && is_string($modelData['name']) && $modelData['name'] !== ''
            ? $modelData['name']
            : $modelData['id'];
    }

    /**
     * Sorts models so generally useful NanoGPT entries appear first.
     *
     * @since 0.1.0
     *
     * @param ModelMetadata $a First model.
     * @param ModelMetadata $b Second model.
     * @return int Comparison result.
     */
    protected function modelSortCallback(ModelMetadata $a, ModelMetadata $b): int
    {
        $aId = $a->getId();
        $bId = $b->getId();

        $aPriority = $this->getPreferredModelPriority($a);
        $bPriority = $this->getPreferredModelPriority($b);
        if ($aPriority !== $bPriority) {
            return $aPriority <=> $bPriority;
        }

        $aPreview = strpos($aId, 'preview') !== false;
        $bPreview = strpos($bId, 'preview') !== false;
        if ($aPreview && !$bPreview) {
            return 1;
        }
        if ($bPreview && !$aPreview) {
            return -1;
        }

        $aImage = $this->modelSupportsImageGeneration($a);
        $bImage = $this->modelSupportsImageGeneration($b);
        if ($aImage && !$bImage) {
            return -1;
        }
        if ($bImage && !$aImage) {
            return 1;
        }

        return strcmp($a->getName(), $b->getName());
    }

    /**
     * Gets the preferred model priority.
     *
     * @since 0.1.1
     *
     * @param ModelMetadata $modelMetadata Model metadata.
     * @return int Priority, with lower values being preferred.
     */
    protected function getPreferredModelPriority(ModelMetadata $modelMetadata): int
    {
        $preferredModelIds = $this->modelSupportsImageGeneration($modelMetadata)
            ? $this->getPreferredImageModelIds()
            : $this->getPreferredTextModelIds();

        $index = array_search($modelMetadata->getId(), $preferredModelIds, true);
        return $index === false ? PHP_INT_MAX : $index;
    }

    /**
     * Gets the preferred image model IDs.
     *
     * @since 0.1.1
     *
     * @return list<string> Preferred image model IDs.
     */
    protected function getPreferredImageModelIds(): array
    {
        return $this->sanitizePreferredModelIds($this->applyPreferredModelIdsFilter(
            'nanogpt_ai_provider_preferred_image_model_ids',
            self::DEFAULT_PREFERRED_IMAGE_MODEL_IDS
        ));
    }

    /**
     * Gets the preferred text model IDs.
     *
     * @since 0.1.1
     *
     * @return list<string> Preferred text model IDs.
     */
    protected function getPreferredTextModelIds(): array
    {
        return $this->sanitizePreferredModelIds($this->applyPreferredModelIdsFilter(
            'nanogpt_ai_provider_preferred_text_model_ids',
            self::DEFAULT_PREFERRED_TEXT_MODEL_IDS
        ));
    }

    /**
     * Applies a WordPress filter if available.
     *
     * @since 0.1.1
     *
     * @param string       $filterName Filter name.
     * @param list<string> $default Default model IDs.
     * @return mixed Filtered model IDs.
     */
    protected function applyPreferredModelIdsFilter(string $filterName, array $default)
    {
        if (!function_exists('apply_filters')) {
            return $default;
        }

        return apply_filters($filterName, $default);
    }

    /**
     * Sanitizes preferred model IDs.
     *
     * @since 0.1.1
     *
     * @param mixed $modelIds Raw model IDs.
     * @return list<string> Sanitized model IDs.
     */
    protected function sanitizePreferredModelIds($modelIds): array
    {
        if (!is_array($modelIds)) {
            return [];
        }

        $sanitized = [];
        foreach ($modelIds as $modelId) {
            if (is_string($modelId) && $modelId !== '') {
                $sanitized[] = $modelId;
            }
        }

        return array_values($sanitized);
    }

    /**
     * Checks whether model metadata supports image generation.
     *
     * @since 0.1.0
     *
     * @param ModelMetadata $modelMetadata Model metadata.
     * @return bool Whether the model supports image generation.
     */
    protected function modelSupportsImageGeneration(ModelMetadata $modelMetadata): bool
    {
        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if ($capability->isImageGeneration()) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.1
     */
    protected function getBaseCacheKey(): string
    {
        $preferredModelIds = array_merge(
            $this->getPreferredImageModelIds(),
            $this->getPreferredTextModelIds()
        );
        $preferredModelIdsHash = md5((string) json_encode($preferredModelIds));

        return 'ai_client_' . AiClient::VERSION . '_' . self::CACHE_VERSION . '_' .
            $preferredModelIdsHash . '_' . md5(static::class);
    }
}
