<?php

declare(strict_types=1);

namespace WordPress\NanoGptAiProvider\Models;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;
use WordPress\NanoGptAiProvider\Provider\NanoGptProvider;
use WordPress\NanoGptAiProvider\Settings\Settings;

/**
 * Class for a NanoGPT image generation model using the OpenAI-compatible Images API.
 *
 * @since 0.1.0
 */
class NanoGptImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel
{
    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        return new Request(
            $method,
            NanoGptProvider::rootUrlWithPath('v1/' . ltrim($path, '/')),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected function prepareGenerateImageParams(array $prompt): array
    {
        $params = parent::prepareGenerateImageParams($prompt);
        $params = $this->applyDefaultImageOptions($params);
        $imageDataUrls = $this->prepareImageDataUrlsParam($prompt);

        if (count($imageDataUrls) === 1) {
            $params['imageDataUrl'] = $imageDataUrls[0];
        } elseif (count($imageDataUrls) > 1) {
            $params['imageDataUrls'] = $imageDataUrls;
        }

        /*
         * NanoGPT's OpenAI-compatible image endpoint accepts response_format.
         * output_format is not documented as a common parameter, so keep MIME
         * type handling inside the AI Client response metadata only.
         */
        unset($params['output_format']);

        return $params;
    }

    /**
     * Applies default image options from plugin settings.
     *
     * Per-request model config custom options win over these defaults.
     *
     * @since 0.3.0
     *
     * @param array<string, mixed> $params Prepared request params.
     * @return array<string, mixed> Request params with defaults applied.
     */
    protected function applyDefaultImageOptions(array $params): array
    {
        $defaultOptions = Settings::getDefaultImageOptions();
        $defaultSize = Settings::getDefaultImageSize();
        if ($defaultSize !== null) {
            $defaultOptions['size'] = $defaultSize;
        }

        foreach ($defaultOptions as $key => $value) {
            if (!is_string($key) || array_key_exists($key, $params)) {
                continue;
            }
            $params[$key] = $value;
        }

        return $params;
    }

    /**
     * Prepares inline image input files for NanoGPT image-to-image models.
     *
     * @since 0.1.0
     *
     * @param list<Message> $messages The image prompt messages.
     * @return list<string> The image data URLs.
     */
    protected function prepareImageDataUrlsParam(array $messages): array
    {
        if (count($messages) !== 1) {
            return [];
        }

        $imageDataUrls = [];
        foreach ($messages[0]->getParts() as $part) {
            if (!$part->getType()->isFile()) {
                continue;
            }

            $file = $part->getFile();
            if (!$file) {
                throw new RuntimeException('The file typed message part must contain a file.');
            }
            if (!$file->isImage()) {
                throw new InvalidArgumentException(
                    sprintf('Unsupported MIME type "%s" for NanoGPT image generation input.', $file->getMimeType())
                );
            }
            if ($file->isRemote()) {
                throw new InvalidArgumentException(
                    'NanoGPT image generation input files must be inline data URLs.'
                );
            }

            $dataUri = $file->getDataUri();
            if (!$dataUri) {
                throw new RuntimeException('The inline image file must contain base64 data.');
            }

            $imageDataUrls[] = $dataUri;
        }

        return $imageDataUrls;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    protected function getResultId(array $responseData): string
    {
        if (isset($responseData['id']) && is_string($responseData['id'])) {
            return $responseData['id'];
        }
        return isset($responseData['created']) && is_int($responseData['created'])
            ? 'img-' . $responseData['created']
            : '';
    }
}
