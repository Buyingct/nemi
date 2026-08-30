<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| NEMI CONTACT STORAGE
|--------------------------------------------------------------------------
*/

function nemiContactsDb(): PDO
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    $databasePath =
        dirname(__DIR__)
        . '/data/nemi.sqlite';

    $db = new PDO(
        'sqlite:' . $databasePath,
        null,
        null,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,
        ]
    );

    $db->exec(
        'PRAGMA foreign_keys = ON'
    );

    $db->exec(
        'PRAGMA busy_timeout = 5000'
    );

    return $db;
}


/*
|--------------------------------------------------------------------------
| GET PERSONAL CONTACTS
|--------------------------------------------------------------------------
*/

function getPersonalContacts(
    string $userId,
    string $search = ''
): array {

    $db = nemiContactsDb();

    $search = trim($search);


    if ($search === '') {

        $statement = $db->prepare(
            '
            SELECT *
            FROM contacts
            WHERE scope = :scope
              AND owner_user_id = :owner_user_id
            ORDER BY
                last_name COLLATE NOCASE,
                first_name COLLATE NOCASE
            '
        );

        $statement->execute([
            ':scope' => 'personal',
            ':owner_user_id' => $userId,
        ]);

        return $statement->fetchAll();
    }


    $statement = $db->prepare(
        '
        SELECT *
        FROM contacts

        WHERE scope = :scope

          AND owner_user_id = :owner_user_id

          AND (
                first_name LIKE :search
             OR last_name LIKE :search
             OR company LIKE :search
             OR email LIKE :search
             OR phone LIKE :search
          )

        ORDER BY
            last_name COLLATE NOCASE,
            first_name COLLATE NOCASE
        '
    );

    $statement->execute([
        ':scope' => 'personal',
        ':owner_user_id' => $userId,
        ':search' => '%' . $search . '%',
    ]);

    return $statement->fetchAll();
}


/*
|--------------------------------------------------------------------------
| GET ONE PERSONAL CONTACT
|--------------------------------------------------------------------------
*/

function getPersonalContact(
    int $contactId,
    string $userId
): ?array {

    $db = nemiContactsDb();

    $statement = $db->prepare(
        '
        SELECT *
        FROM contacts

        WHERE id = :id
          AND scope = :scope
          AND owner_user_id = :owner_user_id

        LIMIT 1
        '
    );

    $statement->execute([
        ':id' => $contactId,
        ':scope' => 'personal',
        ':owner_user_id' => $userId,
    ]);

    $contact = $statement->fetch();

    return $contact ?: null;
}


/*
|--------------------------------------------------------------------------
| CREATE PERSONAL CONTACT
|--------------------------------------------------------------------------
*/

function createPersonalContact(
    string $userId,
    array $data
): int {

    $db = nemiContactsDb();

    $statement = $db->prepare(
        '
        INSERT INTO contacts
        (
            owner_user_id,
            scope,
            contact_type,

            first_name,
            last_name,
            company,

            email,
            phone,

            street,
            city,
            state,
            zip,

            notes,

            created_at,
            updated_at
        )

        VALUES
        (
            :owner_user_id,
            :scope,
            :contact_type,

            :first_name,
            :last_name,
            :company,

            :email,
            :phone,

            :street,
            :city,
            :state,
            :zip,

            :notes,

            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        '
    );

    $statement->execute([
        ':owner_user_id' => $userId,

        ':scope' => 'personal',

        ':contact_type' =>
            trim(
                (string)(
                    $data['contact_type']
                    ?? 'client'
                )
            ),

        ':first_name' =>
            trim(
                (string)(
                    $data['first_name']
                    ?? ''
                )
            ),

        ':last_name' =>
            trim(
                (string)(
                    $data['last_name']
                    ?? ''
                )
            ),

        ':company' =>
            trim(
                (string)(
                    $data['company']
                    ?? ''
                )
            ),

        ':email' =>
            trim(
                (string)(
                    $data['email']
                    ?? ''
                )
            ),

        ':phone' =>
            trim(
                (string)(
                    $data['phone']
                    ?? ''
                )
            ),

        ':street' =>
            trim(
                (string)(
                    $data['street']
                    ?? ''
                )
            ),

        ':city' =>
            trim(
                (string)(
                    $data['city']
                    ?? ''
                )
            ),

        ':state' =>
            trim(
                (string)(
                    $data['state']
                    ?? ''
                )
            ),

        ':zip' =>
            trim(
                (string)(
                    $data['zip']
                    ?? ''
                )
            ),

        ':notes' =>
            trim(
                (string)(
                    $data['notes']
                    ?? ''
                )
            ),
    ]);

    return (int)$db->lastInsertId();
}