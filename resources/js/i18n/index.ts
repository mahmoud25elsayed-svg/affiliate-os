import en from './en.json';

/**
 * Minimal translator for the partner app. Arabic text is the key (same convention as the
 * Laravel side's lang/en.json); English strings live in ./en.json. The locale comes from the
 * shared Inertia props and is set by app.tsx on load and on every navigation.
 */
let locale = 'ar';

const dictionaries: Record<string, Record<string, string>> = { en };

export function setLocale(next?: string | null): void {
    if (next) {
        locale = next;
    }
}

export function getLocale(): string {
    return locale;
}

export function t(key: string, replace: Record<string, string | number> = {}): string {
    let text = dictionaries[locale]?.[key] ?? key;
    for (const [name, value] of Object.entries(replace)) {
        text = text.split(`:${name}`).join(String(value));
    }

    return text;
}
