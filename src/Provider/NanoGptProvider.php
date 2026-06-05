<?php

declare(strict_types=1);

namespace WordPress\NanoGptAiProvider\Provider;

use ReflectionClass;
use ReflectionException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\NanoGptAiProvider\Metadata\NanoGptModelMetadataDirectory;
use WordPress\NanoGptAiProvider\Models\NanoGptImageGenerationModel;
use WordPress\NanoGptAiProvider\Models\NanoGptTextGenerationModel;

/**
 * Class for the AI Provider for NanoGPT.
 *
 * @since 0.1.0
 */
class NanoGptProvider extends AbstractApiProvider
{
    /**
     * Gets the NanoGPT API root URL.
     *
     * @since 0.1.0
     *
     * @return string The NanoGPT API root URL.
     */
    public static function rootUrl(): string
    {
        return 'https://nano-gpt.com';
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function baseUrl(): string
    {
        return self::rootUrl() . '/api/v1';
    }

    /**
     * Constructs a full URL from the NanoGPT root URL.
     *
     * @since 0.1.0
     *
     * @param string $path Path to append to the NanoGPT root URL.
     * @return string The complete URL.
     */
    public static function rootUrlWithPath(string $path = ''): string
    {
        if ($path === '') {
            return self::rootUrl();
        }

        return self::rootUrl() . '/' . ltrim($path, '/');
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if ($capability->isTextGeneration()) {
                return new NanoGptTextGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isImageGeneration()) {
                return new NanoGptImageGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException('Unsupported NanoGPT model capabilities.');
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $providerMetadataArgs = [
            'nanogpt',
            'NanoGPT',
            ProviderTypeEnum::cloud(),
            'https://nano-gpt.com/api',
            RequestAuthenticationMethod::apiKey(),
        ];

        if (self::providerMetadataSupportsDescription()) {
            $providerMetadataArgs[] = function_exists('__')
                ? __('Text and image generation through NanoGPT.', 'ai-provider-for-nanogpt')
                : 'Text and image generation through NanoGPT.';
        }

        return new ProviderMetadata(...$providerMetadataArgs);
    }

    /**
     * Checks whether the AI Client's ProviderMetadata accepts a description argument.
     *
     * The description parameter was not present in every AI Client release, so it
     * is detected from the constructor signature rather than from a version string.
     *
     * @since 0.6.1
     *
     * @return bool Whether a description can be passed to ProviderMetadata.
     */
    protected static function providerMetadataSupportsDescription(): bool
    {
        try {
            $constructor = (new ReflectionClass(ProviderMetadata::class))->getConstructor();
        } catch (ReflectionException $e) {
            return false;
        }

        return $constructor !== null && $constructor->getNumberOfParameters() >= 6;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ListModelsApiBasedProviderAvailability(static::modelMetadataDirectory());
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new NanoGptModelMetadataDirectory();
    }
}
