export function isDarkTheme(): boolean {
    try {
        return localStorage.theme !== 'light';
    } catch {
        return true;
    }
}

export function persistTheme(dark: boolean): void {
    document.documentElement.classList.toggle('dark', dark);
    try {
        localStorage.theme = dark ? 'dark' : 'light';
    } catch {
        //
    }
}
