import { FormField } from '@/components/form-field';
import { useAppToast } from '@/contexts/app-toast-context';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { route } from '@/lib/route';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Globe, Save } from 'lucide-react';
import { useEffect } from 'react';

function SettingsSection({ title, description, children }) {
    return (
        <section className="overflow-hidden rounded-lg border bg-card">
            <div className="border-b bg-muted/30 px-5 py-3">
                <h2 className="text-sm font-semibold text-foreground">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
            </div>
            <div className="grid gap-4 px-5 py-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

function FilePreview({ label, currentUrl, file, onChange, error, accept }) {
    const previewUrl = file ? URL.createObjectURL(file) : currentUrl;

    return (
        <FormField label={label} name={label} error={error} className="sm:col-span-2">
            {previewUrl && (
                <div className="mb-2 flex items-center gap-3 rounded-md border bg-muted/20 p-3">
                    <img src={previewUrl} alt={label} className="size-14 rounded object-contain" />
                    <p className="text-xs text-muted-foreground">Current preview. Upload a new file to replace it.</p>
                </div>
            )}
            <Input type="file" accept={accept} onChange={(event) => onChange(event.target.files?.[0] ?? null)} />
        </FormField>
    );
}

export default function WebsiteSettingsIndex({ settings, mailSettings = {} }) {
    const { flash } = usePage().props;
    const toast = useAppToast();

    const { data, setData, post, processing, errors, transform } = useForm({
        website_name: settings.website_name ?? '',
        phone: settings.phone ?? '',
        email: settings.email ?? '',
        address: settings.address ?? '',
        fb_share_for_withdraw: settings.fb_share_for_withdraw ?? '',
        youtube: settings.youtube ?? '',
        twit: settings.twit ?? '',
        linkend: settings.linkend ?? '',
        topnotice1: settings.topnotice1 ?? '',
        footer_description: settings.footer_description ?? '',
        support_time: settings.support_time ?? '',
        delivery_charge_inside_dhaka: settings.delivery_charge_inside_dhaka ?? '60',
        delivery_charge_outside_dhaka: settings.delivery_charge_outside_dhaka ?? '120',
        meta_tags: settings.meta_tags ?? '',
        meta_description: settings.meta_description ?? '',
        newsletter_enabled: String(settings.newsletter_enabled ?? '1'),
        newsletter_title: settings.newsletter_title ?? '',
        newsletter_description: settings.newsletter_description ?? '',
        newsletter_placeholder: settings.newsletter_placeholder ?? '',
        newsletter_button: settings.newsletter_button ?? '',
        smtp_enabled: String(mailSettings.smtp_enabled ?? '0'),
        smtp_host: mailSettings.smtp_host ?? '',
        smtp_port: mailSettings.smtp_port ?? '587',
        smtp_username: mailSettings.smtp_username ?? '',
        smtp_password: '',
        smtp_encryption: mailSettings.smtp_encryption ?? 'tls',
        mail_from_address: mailSettings.mail_from_address ?? '',
        mail_from_name: mailSettings.mail_from_name ?? '',
        logo: null,
        fav_icon: null,
    });

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    transform((formData) => ({
        ...formData,
        _method: 'put',
    }));

    const submit = (event) => {
        event.preventDefault();

        post(route('setting.website.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Website Settings" />

            <div className="px-2 py-1">
                <div className="mb-4 flex items-center justify-between rounded-lg bg-blue-950 px-5 py-3">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-white/15">
                            <Globe className="size-4 text-white" />
                        </div>
                        <div>
                            <h1 className="text-base font-semibold text-white">Website Settings</h1>
                            <p className="text-xs text-white/60">
                                Manage storefront branding, contact info, delivery charges, and footer content.
                            </p>
                        </div>
                    </div>
                    <Button type="submit" form="website-settings-form" disabled={processing} className="bg-emerald-600 hover:bg-emerald-700">
                        <Save className="size-4" />
                        {processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </div>

                <form id="website-settings-form" onSubmit={submit} className="space-y-4" encType="multipart/form-data">
                    <SettingsSection title="Brand & Identity" description="Site name, logo, and favicon shown across the storefront.">
                        <FormField label="Website name" name="website_name" required error={errors.website_name}>
                            <Input
                                id="website_name"
                                value={data.website_name}
                                onChange={(event) => setData('website_name', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FilePreview
                            label="Logo"
                            currentUrl={settings.logo_url}
                            file={data.logo}
                            onChange={(file) => setData('logo', file)}
                            error={errors.logo}
                            accept="image/*"
                        />

                        <FilePreview
                            label="Favicon"
                            currentUrl={settings.fav_icon_url}
                            file={data.fav_icon}
                            onChange={(file) => setData('fav_icon', file)}
                            error={errors.fav_icon}
                            accept="image/*,.ico"
                        />
                    </SettingsSection>

                    <SettingsSection title="Contact Information" description="Displayed in the footer and contact page.">
                        <FormField label="Phone number" name="phone" error={errors.phone}>
                            <Input id="phone" value={data.phone} onChange={(event) => setData('phone', event.target.value)} className="mt-1" />
                        </FormField>

                        <FormField label="Email address" name="email" error={errors.email}>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(event) => setData('email', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Address" name="address" error={errors.address} className="sm:col-span-2">
                            <Textarea
                                id="address"
                                value={data.address}
                                onChange={(event) => setData('address', event.target.value)}
                                rows={3}
                                className="mt-1"
                            />
                        </FormField>
                    </SettingsSection>

                    <SettingsSection title="Social Links" description="Footer and contact page social icons.">
                        <FormField label="Facebook URL" name="fb_share_for_withdraw" error={errors.fb_share_for_withdraw}>
                            <Input
                                id="fb_share_for_withdraw"
                                value={data.fb_share_for_withdraw}
                                onChange={(event) => setData('fb_share_for_withdraw', event.target.value)}
                                placeholder="https://facebook.com/..."
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="YouTube URL" name="youtube" error={errors.youtube}>
                            <Input
                                id="youtube"
                                value={data.youtube}
                                onChange={(event) => setData('youtube', event.target.value)}
                                placeholder="https://youtube.com/..."
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Twitter / X URL" name="twit" error={errors.twit}>
                            <Input
                                id="twit"
                                value={data.twit}
                                onChange={(event) => setData('twit', event.target.value)}
                                placeholder="https://x.com/..."
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="LinkedIn URL" name="linkend" error={errors.linkend}>
                            <Input
                                id="linkend"
                                value={data.linkend}
                                onChange={(event) => setData('linkend', event.target.value)}
                                placeholder="https://linkedin.com/..."
                                className="mt-1"
                            />
                        </FormField>
                    </SettingsSection>

                    <SettingsSection title="Delivery Charges" description="Used on checkout for inside and outside Dhaka.">
                        <FormField label="Inside Dhaka (৳)" name="delivery_charge_inside_dhaka" required error={errors.delivery_charge_inside_dhaka}>
                            <Input
                                id="delivery_charge_inside_dhaka"
                                type="number"
                                min="0"
                                value={data.delivery_charge_inside_dhaka}
                                onChange={(event) => setData('delivery_charge_inside_dhaka', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Outside Dhaka (৳)" name="delivery_charge_outside_dhaka" required error={errors.delivery_charge_outside_dhaka}>
                            <Input
                                id="delivery_charge_outside_dhaka"
                                type="number"
                                min="0"
                                value={data.delivery_charge_outside_dhaka}
                                onChange={(event) => setData('delivery_charge_outside_dhaka', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>
                    </SettingsSection>

                    <SettingsSection title="Footer & Support" description="Footer text and customer support hours.">
                        <FormField label="Footer description" name="footer_description" error={errors.footer_description} className="sm:col-span-2">
                            <Textarea
                                id="footer_description"
                                value={data.footer_description}
                                onChange={(event) => setData('footer_description', event.target.value)}
                                rows={4}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Support time" name="support_time" error={errors.support_time}>
                            <Input
                                id="support_time"
                                value={data.support_time}
                                onChange={(event) => setData('support_time', event.target.value)}
                                placeholder="Sat–Thu, 10AM–8PM"
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Top banner notice" name="topnotice1" error={errors.topnotice1}>
                            <Input
                                id="topnotice1"
                                value={data.topnotice1}
                                onChange={(event) => setData('topnotice1', event.target.value)}
                                placeholder="Optional notice shown in header/footer"
                                className="mt-1"
                            />
                        </FormField>
                    </SettingsSection>

                    <SettingsSection title="Newsletter" description="Footer newsletter signup section.">
                        <FormField label="Newsletter enabled" name="newsletter_enabled" error={errors.newsletter_enabled}>
                            <select
                                id="newsletter_enabled"
                                value={data.newsletter_enabled}
                                onChange={(event) => setData('newsletter_enabled', event.target.value)}
                                className="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </FormField>

                        <FormField label="Newsletter title" name="newsletter_title" error={errors.newsletter_title}>
                            <Input
                                id="newsletter_title"
                                value={data.newsletter_title}
                                onChange={(event) => setData('newsletter_title', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Newsletter description" name="newsletter_description" error={errors.newsletter_description} className="sm:col-span-2">
                            <Textarea
                                id="newsletter_description"
                                value={data.newsletter_description}
                                onChange={(event) => setData('newsletter_description', event.target.value)}
                                rows={2}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Email placeholder" name="newsletter_placeholder" error={errors.newsletter_placeholder}>
                            <Input
                                id="newsletter_placeholder"
                                value={data.newsletter_placeholder}
                                onChange={(event) => setData('newsletter_placeholder', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Subscribe button text" name="newsletter_button" error={errors.newsletter_button}>
                            <Input
                                id="newsletter_button"
                                value={data.newsletter_button}
                                onChange={(event) => setData('newsletter_button', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>
                    </SettingsSection>

                    <SettingsSection title="SEO" description="Default meta tags for search engines.">
                        <FormField label="Meta title / tags" name="meta_tags" error={errors.meta_tags} className="sm:col-span-2">
                            <Input
                                id="meta_tags"
                                value={data.meta_tags}
                                onChange={(event) => setData('meta_tags', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Meta description" name="meta_description" error={errors.meta_description} className="sm:col-span-2">
                            <Textarea
                                id="meta_description"
                                value={data.meta_description}
                                onChange={(event) => setData('meta_description', event.target.value)}
                                rows={3}
                                className="mt-1"
                            />
                        </FormField>
                    </SettingsSection>

                    <SettingsSection title="Email & SMTP" description="Configure outgoing mail for order and payment notifications.">
                        <FormField label="SMTP enabled" name="smtp_enabled" error={errors.smtp_enabled}>
                            <select
                                id="smtp_enabled"
                                value={data.smtp_enabled}
                                onChange={(event) => setData('smtp_enabled', event.target.value)}
                                className="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="1">Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </FormField>

                        <FormField label="SMTP host" name="smtp_host" error={errors.smtp_host}>
                            <Input
                                id="smtp_host"
                                value={data.smtp_host}
                                onChange={(event) => setData('smtp_host', event.target.value)}
                                placeholder="smtp.mailtrap.io"
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="SMTP port" name="smtp_port" error={errors.smtp_port}>
                            <Input
                                id="smtp_port"
                                type="number"
                                min="1"
                                value={data.smtp_port}
                                onChange={(event) => setData('smtp_port', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="Encryption" name="smtp_encryption" error={errors.smtp_encryption}>
                            <select
                                id="smtp_encryption"
                                value={data.smtp_encryption}
                                onChange={(event) => setData('smtp_encryption', event.target.value)}
                                className="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">None</option>
                            </select>
                        </FormField>

                        <FormField label="SMTP username" name="smtp_username" error={errors.smtp_username}>
                            <Input
                                id="smtp_username"
                                value={data.smtp_username}
                                onChange={(event) => setData('smtp_username', event.target.value)}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="SMTP password" name="smtp_password" error={errors.smtp_password}>
                            <Input
                                id="smtp_password"
                                type="password"
                                value={data.smtp_password}
                                onChange={(event) => setData('smtp_password', event.target.value)}
                                placeholder={mailSettings.smtp_password_configured ? 'Leave blank to keep current password' : 'Enter SMTP password'}
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="From email address" name="mail_from_address" error={errors.mail_from_address}>
                            <Input
                                id="mail_from_address"
                                type="email"
                                value={data.mail_from_address}
                                onChange={(event) => setData('mail_from_address', event.target.value)}
                                placeholder="noreply@yourstore.com"
                                className="mt-1"
                            />
                        </FormField>

                        <FormField label="From name" name="mail_from_name" error={errors.mail_from_name}>
                            <Input
                                id="mail_from_name"
                                value={data.mail_from_name}
                                onChange={(event) => setData('mail_from_name', event.target.value)}
                                placeholder="POS SYSTEM"
                                className="mt-1"
                            />
                        </FormField>

                        <div className="sm:col-span-2 flex flex-wrap gap-2 rounded-md border bg-muted/20 p-4">
                            <p className="w-full text-xs text-muted-foreground">Preview email templates and invoice PDF design:</p>
                            <Button type="button" variant="outline" size="sm" asChild>
                                <a href={route('setting.website.preview-email.order')} target="_blank" rel="noreferrer">
                                    Order Email Preview
                                </a>
                            </Button>
                            <Button type="button" variant="outline" size="sm" asChild>
                                <a href={route('setting.website.preview-email.payment')} target="_blank" rel="noreferrer">
                                    Payment Email Preview
                                </a>
                            </Button>
                            <Button type="button" variant="outline" size="sm" asChild>
                                <a href={route('setting.website.preview-invoice')} target="_blank" rel="noreferrer">
                                    Invoice PDF Preview
                                </a>
                            </Button>
                        </div>
                    </SettingsSection>
                </form>
            </div>
        </>
    );
}
