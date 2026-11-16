<?php

declare(strict_types=1);

namespace Nex\Validation;

use Nex\Validation\Contracts\ValidationResultInterface;
use Nex\Validation\Contracts\ValidatorInterface;
use Nex\Validation\Exceptions\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

abstract class FormRequest
{
    protected ServerRequestInterface $request;
    protected ValidationResultInterface $result;

    public function __construct(
        ServerRequestInterface $request,
        ?ValidatorInterface $validator = null
    ) {
        $this->request = $request;
        $validator ??= new Validator();

        $data = $this->extractData($request);
        $this->result = $validator->validate($data, $this->rules());

        if ($this->result->fails()) {
            throw new ValidationException($this->result->errors());
        }
    }

    /**
     * @return array<string, list<string>|string>
     */
    abstract public function rules(): array;

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->result->validated();
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function __get(string $key): mixed
    {
        $validated = $this->result->validated();
        return $validated[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractData(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        $data = is_array($body) ? $body : [];
        $query = $request->getQueryParams();

        return array_merge($query, $data);
    }
}
