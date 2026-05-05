<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use JsonException;
use PDO;
use Throwable;

final class AdminController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function messages(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $filters = $this->messageFilters();

            $countSql = 'SELECT COUNT(*) FROM messages' . $filters['where'];
            $countStatement = $this->pdo->prepare($countSql);
            $this->bindMessageFilters($countStatement, $filters['params']);
            $countStatement->execute();
            $total = (int) $countStatement->fetchColumn();

            $sql = 'SELECT id, name, email, subject, message, is_read, created_at
                    FROM messages'
                . $filters['where']
                . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';

            $statement = $this->pdo->prepare($sql);
            $this->bindMessageFilters($statement, $filters['params']);
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();

            $data = array_map(
                fn (array $message): array => $this->formatMessage($message),
                $statement->fetchAll()
            );

            header('X-Total-Count: ' . $total);

            Response::json([
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => (int) ceil($total / $limit),
                ],
            ]);
        } catch (Throwable) {
            $this->serverError();
        }
    }

    public function showMessage(string $id): void
    {
        try {
            $message = $this->findMessage((int) $id);

            if ($message === null) {
                $this->notFound();
                return;
            }

            if ((int) $message['is_read'] === 0) {
                $statement = $this->pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = :id');
                $statement->execute(['id' => (int) $id]);
                $message['is_read'] = 1;
            }

            Response::json($this->formatMessage($message));
        } catch (Throwable) {
            $this->serverError();
        }
    }

    public function updateReadStatus(string $id): void
    {
        try {
            $payload = $this->jsonBody();
            $isRead = (int) ($payload['is_read'] ?? -1);

            if (!in_array($isRead, [0, 1], true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid read status',
                ], 422);
                return;
            }

            if ($this->findMessage((int) $id) === null) {
                $this->notFound();
                return;
            }

            $statement = $this->pdo->prepare('UPDATE messages SET is_read = :is_read WHERE id = :id');
            $statement->bindValue('is_read', $isRead, PDO::PARAM_INT);
            $statement->bindValue('id', (int) $id, PDO::PARAM_INT);
            $statement->execute();

            $updated = $this->findMessage((int) $id);

            Response::json($this->formatMessage($updated));
        } catch (JsonException) {
            Response::json([
                'success' => false,
                'message' => 'Invalid JSON body',
            ], 422);
        } catch (Throwable) {
            $this->serverError();
        }
    }

    public function deleteMessage(string $id): void
    {
        try {
            $statement = $this->pdo->prepare('DELETE FROM messages WHERE id = :id');
            $statement->bindValue('id', (int) $id, PDO::PARAM_INT);
            $statement->execute();

            http_response_code(204);
        } catch (Throwable) {
            $this->serverError();
        }
    }

    public function stats(): void
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread,
                    SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS today,
                    SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURRENT_DATE, 1) THEN 1 ELSE 0 END) AS this_week
                 FROM messages'
            );
            $statement->execute();
            $stats = $statement->fetch();

            Response::json([
                'total' => (int) ($stats['total'] ?? 0),
                'unread' => (int) ($stats['unread'] ?? 0),
                'today' => (int) ($stats['today'] ?? 0),
                'this_week' => (int) ($stats['this_week'] ?? 0),
            ]);
        } catch (Throwable) {
            $this->serverError();
        }
    }

    /**
     * @return array{where: string, params: array<string, string|int>}
     */
    private function messageFilters(): array
    {
        $where = [];
        $params = [];
        $status = (string) ($_GET['status'] ?? 'all');
        $search = trim((string) ($_GET['search'] ?? ''));

        if ($status === 'unread') {
            $where[] = 'is_read = :is_read';
            $params['is_read'] = 0;
        } elseif ($status === 'read') {
            $where[] = 'is_read = :is_read';
            $params['is_read'] = 1;
        }

        if ($search !== '') {
            $where[] = '(name LIKE :search OR email LIKE :search OR subject LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        return [
            'where' => $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
            'params' => $params,
        ];
    }

    /**
     * @param array<string, string|int> $params
     */
    private function bindMessageFilters(\PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($key, $value, $type);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMessage(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, subject, message, is_read, created_at
             FROM messages
             WHERE id = :id
             LIMIT 1'
        );
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();

        $message = $statement->fetch();

        return $message === false ? null : $message;
    }

    /**
     * @param array<string, mixed>|null $message
     * @return array<string, mixed>
     */
    private function formatMessage(?array $message): array
    {
        if ($message === null) {
            return [];
        }

        return [
            'id' => (int) $message['id'],
            'name' => (string) $message['name'],
            'email' => (string) $message['email'],
            'subject' => (string) $message['subject'],
            'message' => (string) $message['message'],
            'is_read' => (int) $message['is_read'],
            'created_at' => date(DATE_ATOM, strtotime((string) $message['created_at'])),
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function jsonBody(): array
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function notFound(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Not found',
        ], 404);
    }

    private function serverError(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Server error',
        ], 500);
    }
}
