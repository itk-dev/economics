<?php

namespace App\Service\ProjectTracker;

use App\Service\Exceptions\ApiServiceException;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
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
     * @throws ApiServiceException
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
     * @throws ApiServiceException
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
     * @throws ApiServiceException
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
     * @throws ApiServiceException
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
     * @throws ApiServiceException
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
     * @throws ApiServiceException
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
     * @throws ApiServiceException
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
     * Create a jira project.
     *
     * See https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/#api/2/project-createProject
     *
     * @param array $data
     *
     * @return ?string
     *
     * @throws ApiServiceException
     */
    public function createJiraProject(array $data): ?string
    {
        $projectKey = strtoupper($data['form']['project_key']);
        $project = [
            "key" => $projectKey,
            "name" => $data['form']['project_name'],
            "projectTypeKey" => "software",
            "projectTemplateKey" => "com.pyxis.greenhopper.jira:basic-software-development-template",
            "description" => $data['form']['description'],
            "lead" => $data['selectedTeamConfig']['team_lead'],
            "assigneeType" => "UNASSIGNED",
            "avatarId" => 10324, // Default avatar image
            "permissionScheme" => $data['selectedTeamConfig']['permission_scheme'],
            "notificationScheme" => 10000, // Default Notification Scheme
            "workflowSchemeId" => $data['selectedTeamConfig']['workflow_scheme'],
            "categoryId" => $data['selectedTeamConfig']['project_category'],
        ];

        $response = $this->post('/rest/api/2/project', $project);

        return ('project was created' === $response->message) ? $projectKey : null;
    }








    /**
     * Get from Jira.
     *
     * @TODO: Wrap the call in request function, they er 99% the same code.
     *
     * @param string $path
     * @param array $query
     *
     * @return mixed
     *
     * @throws ApiServiceException
     */
    private function get(string $path, array $query = []): mixed
    {
        try {
            $response = $this->projectTrackerApi->request('GET', $path,
                [
                    'query' => $query
                ]
            );

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                    }
                    break;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }

        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
            throw new ApiServiceException($e->getMessage(), $e->getCode(), $e);
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
     *
     * @throws ApiServiceException
     */
    private function post(string $path, array $data): mixed
    {
        try {
            $response = $this->projectTrackerApi->request('POST', $path,
                [
                    'json' => $data,
                ]
            );

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                    }
                    break;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }
        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
            throw new ApiServiceException($e->getMessage(), $e->getCode(), $e);
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
     *
     * @throws ApiServiceException
     */
    private function put(string $path, array $data): mixed
    {
        try {
            $response = $this->projectTrackerApi->request('PUT', $path,
                [
                    'json' => $data,
                ]
            );

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                    }
                    break;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }

        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
            throw new ApiServiceException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Delete in Jira.
     *
     * @param string $path
     *
     * @return mixed
     *
     * @throws ApiServiceException
     */
    private function delete(string $path): mixed
    {
        try {
            $response = $this->projectTrackerApi->request('DELETE', $path);

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                    }
                    break;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }

        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
            throw new ApiServiceException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }
}
