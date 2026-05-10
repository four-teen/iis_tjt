<?php

function normalize_bulk_ids($ids)
{
    if (!is_array($ids)) {
        return [];
    }

    $normalized = [];

    foreach ($ids as $id) {
        $id = (int) $id;

        if ($id > 0 && !in_array($id, $normalized, true)) {
            $normalized[] = $id;
        }
    }

    return $normalized;
}

function bulk_delete_records($ids, callable $findRecord, callable $deleteRecord)
{
    $result = [
        'selected' => count($ids),
        'deleted' => 0,
        'missing' => 0,
        'blocked' => 0,
        'errors' => [],
    ];

    foreach ($ids as $id) {
        $record = $findRecord($id);

        if (!$record) {
            $result['missing']++;
            continue;
        }

        try {
            if ($deleteRecord($id, $record)) {
                $result['deleted']++;
            } else {
                $result['blocked']++;
            }
        } catch (Throwable $error) {
            $result['blocked']++;

            if (count($result['errors']) < 3) {
                $result['errors'][] = $error->getMessage();
            }
        }
    }

    return $result;
}

function flash_bulk_delete_result($entityName, array $result)
{
    if ($result['selected'] === 0) {
        flash('error', 'Select at least one ' . $entityName . ' to delete.');
        return;
    }

    if ($result['deleted'] > 0) {
        flash('success', $result['deleted'] . ' ' . $entityName . ($result['deleted'] === 1 ? ' was' : 's were') . ' deleted.');
    }

    $notDeleted = (int) $result['blocked'] + (int) $result['missing'];

    if ($notDeleted > 0) {
        $message = $notDeleted . ' selected ' . $entityName . ($notDeleted === 1 ? ' was' : 's were') . ' not deleted.';

        if ($result['errors']) {
            $message .= ' ' . implode(' ', array_unique($result['errors']));
        }

        flash('error', $message);
    }

    if ($result['deleted'] === 0 && $notDeleted === 0) {
        flash('error', 'No ' . $entityName . ' records were deleted.');
    }
}
