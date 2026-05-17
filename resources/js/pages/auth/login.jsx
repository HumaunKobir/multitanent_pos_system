import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';

import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { route, routeForm } from '@/lib/route';

export default function Login({ status, canResetPassword, canRegister }) {
    useEffect(() => {
        setLayoutProps({
            title: 'Log in to your account',
            description: 'Enter your email and password below to log in',
        });
    }, []);

    return (
        <>
            <Head title="Log in" />

            {status ? (
                <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
            ) : null}

            <Form
                {...routeForm('login.store')}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <LoginFormFields
                            errors={errors}
                            processing={processing}
                            canResetPassword={canResetPassword}
                        />

                        {canRegister ? (
                            <div className="text-center text-sm text-muted-foreground">
                                Don&apos;t have an account?{' '}
                                <TextLink href={route('register')} tabIndex={5}>
                                    Sign up
                                </TextLink>
                            </div>
                        ) : null}
                    </>
                )}
            </Form>
        </>
    );
}

function LoginFormFields({ errors, processing, canResetPassword }) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autoFocus
                    tabIndex={1}
                    autoComplete="email"
                    placeholder="email@example.com"
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <div className="flex items-center">
                    <Label htmlFor="password">Password</Label>
                    {canResetPassword ? (
                        <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                            Forgot password?
                        </TextLink>
                    ) : null}
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    tabIndex={2}
                    autoComplete="current-password"
                    placeholder="Password"
                />
                <InputError message={errors.password} />
            </div>

            <div className="flex items-center gap-3">
                <input
                    type="checkbox"
                    id="remember"
                    name="remember"
                    tabIndex={3}
                    className="size-4 rounded-none border border-input accent-primary"
                />
                <Label htmlFor="remember">Remember me</Label>
            </div>

            <Button
                type="submit"
                className="mt-4 w-full"
                tabIndex={4}
                disabled={processing}
                data-test="login-button"
            >
                {processing ? <Spinner /> : null}
                <span>Log in</span>
            </Button>
        </div>
    );
}
