import { usePage } from '@inertiajs/react';
import { PageProps } from '../types';

export function Flash() {
    const { flash, errors } = usePage<PageProps>().props;
    const errorList = Object.values(errors || {});

    return (
        <>
            {flash.status && <div className="flash-success mt-5">{flash.status}</div>}
            {errorList.length > 0 && (
                <div className="flash-danger mt-5">
                    <ul className="list-inside list-disc space-y-1">{errorList.map((e) => <li key={e}>{e}</li>)}</ul>
                </div>
            )}
        </>
    );
}
