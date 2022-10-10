<?php

namespace App\Service\ProjectTracker;

use JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JiraApiService implements ApiServiceInterface
{

    public function __construct(
        protected readonly HttpClientInterface $projectTrackerApi,
        $customFieldMappings
    ) {
    }

    /**
     * @return mixed
     *
     * @throws JsonException
     */
    public function getAllProjectCategories(): mixed
    {
        return $this->get('/rest/api/2/projectCategory');
    }

    /**
     * Get all accounts.
     *
     * @return mixed
     *
     * @throws JsonException
     */
    public function getAllAccounts(): mixed
    {
        return $this->get('/rest/tempo-accounts/1/account/');
    }

    /**
     * Get all accounts.
     *
     * @return mixed
     *
     * @throws JsonException
     */
    public function getAllCustomers(): mixed
    {
        return $this->get('/rest/tempo-accounts/1/customer/');
    }


    /**
     * Get all projects, including archived.
     *
     * @return mixed
     *
     * @throws JsonException
     */
    public function getAllProjects(): mixed
    {
        return $this->get('/rest/api/2/project');
    }

    /**
     * Get project.
     *
     * @param $key
     *   A project key or id
     *
     * @return mixed
     *
     * @throws JsonException
     */
    public function getProject($key): mixed
    {
        return $this->get('/rest/api/2/project/'.$key);
    }

    /**
     * Get current user permissions.
     *
     * @return mixed
     *
     * @throws JsonException
     */
    public function getCurrentUserPermissions(): mixed
    {
        return $this->get('/rest/api/2/mypermissions');
    }

    /**
     * Get list of allowed permissions for current user.
     *
     * @return array
     *
     * @throws JsonException
     */
    public function getPermissionsList(): array
    {
        $list = [];
        $restPermissions = $this->getCurrentUserPermissions();
        if (isset($restPermissions->permissions) && \is_object($restPermissions->permissions)) {
            foreach ($restPermissions->permissions as $permission_name => $value) {
                if (isset($value->havePermission) && true === $value->havePermission) {
                    $list[] = $permission_name;
                }
            }
        }

        return $list;
    }









    /**
     * Get from Jira.
     *
     * @param string $path
     * @param array $query
     *
     * @return mixed
     *
     * @throws JsonException
     */
    private function get(string $path, array $query = []): mixed
    {
        $response = $this->projectTrackerApi->request('GET', $path, ['query' => $query]);

        if ($body = $response->getContent()) {
            return json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        }

        return null;
    }

    /**
     * Post to Jira.
     *
     * @param string $path
     * @param array $data
     *
     * @return mixed
     */
    private function post(string $path, array $data): mixed
    {
        $response = $this->projectTrackerApi->request(
            'POST',
            $path,
            [
                'json' => $data,
            ]
        );

        if ($body = $response->getContent()) {
            return json_decode($body);
        }

        return null;
    }


    /**
     * Put to Jira.
     *
     * @param string $path
     * @param array $data
     *
     * @return mixed
     */
    private function put(string $path, array $data): mixed
    {
        $response = $this->projectTrackerApi->request(
            'PUT',
            $path,
            [
                'json' => $data,
            ]
        );

        if ($body = $response->getContent()) {
            return json_decode($body);
        }

        return null;
    }

    /**
     * Delete in Jira.
     *
     * @param string $path
     *
     * @return mixed
     */
    private function delete(string $path): mixed
    {
        $response = $this->projectTrackerApi->request('DELETE', $path);
        if ($body = $response->getContent()) {
            return json_decode($body);
        }

        return null;
    }
}
