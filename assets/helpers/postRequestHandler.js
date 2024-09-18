export const postRequestHandler = async (updateUrl, data = null) => {
    let result = {
        success: false,
        status: null,
        data: null,
        error: null
    };

    try {
        const response = await fetch(updateUrl, {
            method: "POST",
            mode: "same-origin",
            cache: "no-cache",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            redirect: "follow",
            referrerPolicy: "no-referrer",
            body: data && JSON.stringify(data),
        });

        result.status = response.status;

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message);
        } else {
            result.success = true;
            result.data = await response.json();
        }
    } catch (error) {
        console.error(error.message);
        result.error = error.message;
    }

    return result;
};
