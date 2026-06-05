<?php

declare(strict_types=1);

namespace WordPress\NanoGptAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\NanoGptAiProvider\Provider\NanoGptProvider;

/**
 * Class for a NanoGPT text generation model using Chat Completions.
 *
 * @since 0.1.0
 */
class NanoGptTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
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
            NanoGptProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
