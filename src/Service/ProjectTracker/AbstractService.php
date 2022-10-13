<?php

namespace App\Service\ProjectTracker;

/**
 * @deprecated this class should not be used
 */
abstract class AbstractService
{
    protected $customFieldMappings;

    public function __construct(
        $customFieldMappings
    ) {
        $this->customFieldMappings = $customFieldMappings;
    }

//    /**
//     * Get all projects.
//     *
//     * @return array
//     */
//    public function getProjects()
//    {
//        $projects = [];
//
//        $results = $this->get('/rest/api/2/project');
//
//        foreach ($results as $result) {
//            if (!isset($result->projectCategory) || 'Lukket' !== $result->projectCategory->name) {
//                $result->url = parse_url(
//                        $result->self,
//                        \PHP_URL_SCHEME
//                    ).'://'.parse_url(
//                        $result->self,
//                        \PHP_URL_HOST
//                    ).'/browse/'.$result->key;
//                $projects[] = $result;
//            }
//        }
//
//        return $projects;
//    }

//    /**
//     * Get all boards.
//     *
//     * @return array
//     */
//    public function getAllBoards()
//    {
//        $boards = [];
//
//        $start = 0;
//        while ($start < 1000) {
//            $result = $this->get('/rest/agile/1.0/board', [
//                'maxResults' => 50,
//                'startAt' => $start,
//            ]);
//            $boards = array_merge($boards, $result->values);
//
//            if ($result->isLast) {
//                break;
//            }
//
//            $start = $start + 50;
//        }
//
//        return $boards;
//    }
//
//    /**
//     * Get board by id.
//     *
//     * @param $boardId
//     *
//     * @return mixed
//     */
//    public function getBoard($boardId)
//    {
//        return $this->get('/rest/agile/1.0/board/'.$boardId);
//    }
//
//    /**
//     * Get all worklogs for project.
//     *
//     * @param $projectId
//     * @param string $from
//     * @param string $to
//     *
//     * @return mixed
//     */
//    public function getProjectWorklogs($projectId, $from = '2000-01-01', $to = '3000-01-01')
//    {
//        $worklogs = $this->post('rest/tempo-timesheets/4/worklogs/search', [
//            'from' => $from,
//            'to' => $to,
//            'projectId' => [$projectId],
//        ]);
//
//        return $worklogs;
//    }
//
//    /**
//     * Get all worklogs for issue.
//     *
//     * @param $issueId
//     * @param string $from
//     * @param string $to
//     *
//     * @return array
//     */
//    public function getIssueWorklogs($issueId, $from = '2000-01-01', $to = '3000-01-01')
//    {
//        $worklogs = $this->post('rest/tempo-timesheets/4/worklogs/search', [
//            'from' => $from,
//            'to' => $to,
//            'taskId' => [$issueId],
//        ]);
//
//        return $worklogs;
//    }
//
//    public function getAccount($accountId)
//    {
//        return $this->get('/rest/tempo-accounts/1/account/'.$accountId.'/');
//    }
//
//    public function getRateTableByAccount($accountId)
//    {
//        return $this->get('/rest/tempo-accounts/1/ratetable', [
//            'scopeId' => $accountId,
//            'scopeType' => 'ACCOUNT',
//        ]);
//    }
//
//    public function getAccountDefaultPrice($accountId)
//    {
//        $rateTable = $this->getRateTableByAccount($accountId);
//
//        foreach ($rateTable->rates as $rate) {
//            if ('DEFAULT_RATE' === $rate->link->type) {
//                return $rate->amount;
//            }
//        }
//
//        return null;
//    }
//
//    public function getAccountIdsByProject($projectId)
//    {
//        $projectLinks = $this->get('/rest/tempo-accounts/1/link/project/'.$projectId);
//
//        return array_reduce($projectLinks, function ($carry, $item) {
//            $carry[] = $item->accountId;
//
//            return $carry;
//        }, []);
//    }
//
//    /**
//     * Get all Jira custom fields.
//     *
//     * @return mixed
//     */
//    public function getCustomFields()
//    {
//        return $this->get('/rest/api/2/field');
//    }
//
//    /**
//     * Get custom field id by field name.
//     *
//     * These refer to mappings set in jira_economics.local.yaml.
//     *
//     * @param string $fieldName
//     *
//     * @return string
//     */
//    public function getCustomFieldId($fieldName)
//    {
//        return isset($this->customFieldMappings[$fieldName]) ? 'customfield_'.$this->customFieldMappings[$fieldName] : false;
//    }
//
//    /**
//     * Get tempo custom fields.
//     *
//     * @return mixed
//     */
//    public function getTempoCustomFields()
//    {
//        $customFields = $this->get('/rest/tempo-accounts/1/field/');
//
//        return $customFields;
//    }
//
//    /**
//     * Get all tempo categories.
//     *
//     * @return mixed
//     */
//    public function getTempoCategories()
//    {
//        $customFields = $this->get('/rest/tempo-accounts/1/category/');
//
//        return $customFields;
//    }
//
//    /**
//     * Get customer by id.
//     *
//     * @param $id
//     *
//     * @return mixed
//     */
//    public function getTempoCustomer($id)
//    {
//        return $this->get('/rest/tempo-accounts/1/customer/'.$id);
//    }
//
//    /**
//     * Get user from name.
//     *
//     * @return mixed
//     */
//    public function getUser($username)
//    {
//        try {
//            $result = $this->get('/rest/api/2/user', ['username' => $username]);
//        } catch (RequestException $e) {
//            return null;
//        }
//
//        return $result;
//    }
//
//    /**
//     * Get users from search.
//     *
//     * @param $email
//     *   An email from a portal user
//     *
//     * @return mixed|null
//     *                    A Jira user or NULL
//     */
//    public function searchUser($email)
//    {
//        $result = $this->get('/rest/api/2/user/search', ['username' => $email]);
//        // The call searches on name, username and email which may produce.
//        // multiple results. We only want those matching on email.
//        if (!empty($result)) {
//            foreach ($result as $jiraUser) {
//                if ($jiraUser->emailAddress === $email) {
//                    return $jiraUser;
//                }
//            }
//        }
//
//        return null;
//    }
//
//    /**
//     * Create a new jira user.
//     *
//     * @return mixed
//     */
//    public function createUser($user)
//    {
//        $result = $this->post('/rest/api/2/user', $user);
//
//        return $result;
//    }
//
//    public function search(array $query)
//    {
//        $result = $this->get('/rest/api/2/search', $query);
//
//        return $result;
//    }
//
//    public function getIssueUrl($issue)
//    {
//        $key = $issue->key ?? $issue;
//
//        return $this->jiraUrl.'/browse/'.$key;
//    }
//
//    /**
//     * @see https://docs.atlassian.com/software/jira/docs/api/REST/8.3.1/?_ga=2.202569298.2139473575.1564917078-393255252.1550779361#api/2/issue-getIssuePickerResource
//     *
//     * @return mixed
//     */
//    public function issuePicker(string $project, string $query)
//    {
//        $result = $this->get('/rest/api/2/issue/picker', [
//            'currentJQL' => 'project="'.$project.'"',
//            'query' => $query,
//        ]);
//
//        return $result;
//    }
//
//    public function getIssue($issueIdOrKey)
//    {
//        $result = $this->get('/rest/api/2/issue/'.$issueIdOrKey);
//
//        return $result;
//    }
//
//    /**
//     * @see http://developer.tempo.io/doc/core/api/rest/latest/#1349331745
//     */
//    public function getExpenseCategories()
//    {
//        $result = $this->get('/rest/tempo-core/1/expense/category/');
//
//        return $result;
//    }
//
//    public function getExpenseCategory(int $id)
//    {
//        $categories = $this->getExpenseCategories();
//
//        foreach ($categories as $category) {
//            if ($id === $category->id) {
//                return $category;
//            }
//        }
//
//        return null;
//    }
//
//    public function getExpenseCategoryByName(string $name)
//    {
//        $categories = $this->getExpenseCategories();
//
//        foreach ($categories as $category) {
//            if ($name === $category->name) {
//                return $category;
//            }
//        }
//
//        return null;
//    }
//
//    public function createExpenseCategory(ExpenseCategory $category)
//    {
//        $result = $this->post('/rest/tempo-core/1/expense/category/', [
//            'name' => $category->getName(),
//        ]);
//
//        return $result;
//    }
//
//    public function updateExpenseCategory(ExpenseCategory $category)
//    {
//        $result = $this->put(
//            '/rest/tempo-core/1/expense/category/'.$category->getId().'/',
//            [
//                'name' => $category->getName(),
//            ]
//        );
//
//        return $result;
//    }
//
//    public function deleteExpenseCategory(ExpenseCategory $category)
//    {
//        $result = $this->delete('/rest/tempo-core/1/expense/category/'.$category->getId().'/');
//
//        return $result;
//    }
//
//    /**
//     * @see http://developer.tempo.io/doc/core/api/rest/latest/#1349331745
//     */
//    public function getExpenses(array $query = [])
//    {
//        $result = $this->get('/rest/tempo-core/1/expense', $query);
//
//        return $result;
//    }
//
//    public function createExpense(array $data)
//    {
//        $category = $data['category'] ?? null;
//        if (!$category instanceof Category) {
//            throw new \RuntimeException('Invalid or missing category');
//        }
//        $data = [
//            'expenseCategory' => [
//                'id' => $category->getId(),
//            ],
//            'scope' => [
//                'scopeType' => $data['scope_type'],
//                'scopeId' => $data['scope_id'],
//            ],
//            'amount' => round($data['quantity'] * $category->getUnitPrice(), 2),
//            'description' => $data['description'] ?? $category->getName(),
//            'date' => (new \DateTime())->format(\DateTime::ATOM),
//        ];
//        $result = $this->post('/rest/tempo-core/1/expense/', $data);
//
//        return $result;
//    }
}
