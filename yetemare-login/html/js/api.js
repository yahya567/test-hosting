const API_HOST = 'https://app.falconvas.com';
const SHORTCODE = '9643';

async function loginUser(msisdn, password) {
    try {
        const params = new URLSearchParams({
            msisdn,
            password,
            shortcode: SHORTCODE,
        });

        const response = await fetch(`${API_HOST}/check?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        return {
            status: 'error',
            message: error instanceof Error ? error.message : 'Network error occurred',
        };
    }
}

async function resetPassword(msisdn) {
    try {
        const params = new URLSearchParams({
            msisdn,
            shortcode: SHORTCODE,
        });

        const response = await fetch(`${API_HOST}/reset?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        return {
            status: 'error',
            message: error instanceof Error ? error.message : 'Network error occurred',
        };
    }
}
