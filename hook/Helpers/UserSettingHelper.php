<?php

namespace TLC\Hook\Helpers;

class UserSettingHelper
{
    public const KEY_LANGUAGE = 'language';
    public const KEY_IN_APP_ACTIVITY_NOTIFICATIONS = 'inAppActivityNotifications';

    public static function getDefinitions(): array
    {
        return [
            [
                'name' => self::KEY_LANGUAGE,
                'defaultValue' => 'en',
                'type' => 'string',
                'category' => 'general',
                'categoryLabel' => [
                    'en' => 'General',
                    'fr' => 'Général',
                    'es' => 'General',
                    'de' => 'Allgemein',
                    'it' => 'Generale',
                ],
                'description' => [
                    'en' => 'Preferred language for the application.',
                    'fr' => 'Langue préférée pour l’application.',
                    'es' => 'Idioma preferido para la aplicación.',
                    'de' => 'Bevorzugte Sprache für die Anwendung.',
                    'it' => 'Lingua preferita per l’applicazione.',
                ],
            ],
            [
                'name' => self::KEY_IN_APP_ACTIVITY_NOTIFICATIONS,
                'defaultValue' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'categoryLabel' => [
                    'en' => 'Notifications',
                    'fr' => 'Notifications',
                    'es' => 'Notificaciones',
                    'de' => 'Benachrichtigungen',
                    'it' => 'Notifiche',
                ],
                'description' => [
                    'en' => 'Receive notifications for in-app activities.',
                    'fr' => 'Recevoir des notifications pour les activités dans l’application.',
                    'es' => 'Recibir notificaciones para actividades dentro de la aplicación.',
                    'de' => 'Benachrichtigungen für Aktivitäten in der App erhalten.',
                    'it' => 'Ricevi notifiche per le attività nell’applicazione.',
                ],
            ],
        ];
    }
}
