import { useState, useEffect } from 'react';
import { useLocation } from 'wouter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from 'sonner';
import { translations, Language } from '@/lib/translations';
import { loginUser, resetPassword } from '@/lib/api';
import { Monitor, Lock, Phone, Globe } from 'lucide-react';

/**
 * Login Page - Professional Tech Design
 * Features:
 * - Multilingual support (English, Amharic, Oromo, Tigrinya)
 * - Login with phone number and password
 * - Password reset functionality
 * - Terms of Service link
 * - Geometric pattern accent (circuit-inspired)
 */
export default function Login() {
  const [, setLocation] = useLocation();
  const [language, setLanguage] = useState<Language>('en');
  const [isResetMode, setIsResetMode] = useState(false);
  const [phoneNumber, setPhoneNumber] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const t = translations[language];

  // Handle login submission
  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setMessage(null);

    if (!phoneNumber || !password) {
      setMessage({ type: 'error', text: 'Please fill in all fields' });
      setIsLoading(false);
      return;
    }

    const response = await loginUser(phoneNumber, password);

    if (response.status === 'success') {
      setMessage({ type: 'success', text: response.message });
      toast.success(response.message);
      
      // Simulate login delay then redirect
      setTimeout(() => {
        // In a real app, you'd store the auth token and redirect to dashboard
        setLocation('/dashboard');
      }, 1500);
    } else {
      setMessage({ type: 'error', text: response.message });
      toast.error(response.message);
      setIsLoading(false);
    }
  };

  // Handle password reset submission
  const handlePasswordReset = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setMessage(null);

    if (!phoneNumber) {
      setMessage({ type: 'error', text: 'Please enter your phone number' });
      setIsLoading(false);
      return;
    }

    const response = await resetPassword(phoneNumber);

    if (response.status === 'success') {
      setMessage({ type: 'success', text: response.message });
      toast.success(response.message);
      
      // Redirect to login after 2 seconds
      setTimeout(() => {
        setIsResetMode(false);
        setPhoneNumber('');
        setPassword('');
        setMessage(null);
        setIsLoading(false);
      }, 2000);
    } else {
      setMessage({ type: 'error', text: response.message });
      toast.error(response.message);
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-background to-muted flex items-center justify-center p-4 relative overflow-hidden">
      {/* Geometric pattern accent - circuit-inspired */}
      <div className="absolute top-0 right-0 w-96 h-96 opacity-5 pointer-events-none">
        <svg viewBox="0 0 400 400" className="w-full h-full">
          <defs>
            <pattern id="hexagon" x="0" y="0" width="100" height="100" patternUnits="userSpaceOnUse">
              <path d="M50,10 L90,30 L90,70 L50,90 L10,70 L10,30 Z" fill="none" stroke="currentColor" strokeWidth="2"/>
            </pattern>
          </defs>
          <rect width="400" height="400" fill="url(#hexagon)" className="text-primary"/>
        </svg>
      </div>

      {/* Language selector - top right */}
      <div className="absolute top-6 right-6 z-10">
        <div className="flex items-center gap-2 bg-card border border-border rounded-lg p-2 shadow-sm">
          <Globe size={18} className="text-primary" />
          <select
            value={language}
            onChange={(e) => setLanguage(e.target.value as Language)}
            className="bg-transparent text-sm font-medium text-foreground cursor-pointer outline-none border-none"
          >
            <option value="en">{translations.en.language}</option>
            <option value="am">{translations.am.language}</option>
            <option value="or">{translations.or.language}</option>
            <option value="ti">{translations.ti.language}</option>
          </select>
        </div>
      </div>

      {/* Main card */}
      <div className="w-full max-w-md relative z-10">
        <div className="bg-card border border-border rounded-xl shadow-lg overflow-hidden">
          {/* Header section with logo area */}
          <div className="bg-gradient-to-r from-primary to-primary/80 p-8 text-center">
            <div className="flex justify-center mb-4">
              <div className="bg-primary-foreground p-3 rounded-lg">
                <Monitor size={32} className="text-primary" />
              </div>
            </div>
            <h1 className="text-3xl font-bold text-primary-foreground">{t.appName}</h1>
            <p className="text-primary-foreground/80 text-sm mt-2">{t.tagline}</p>
          </div>

          {/* Form section */}
          <div className="p-8">
            {/* Form title */}
            <h2 className="text-xl font-semibold text-foreground mb-6 text-center">
              {isResetMode ? t.passwordResetTitle : t.login}
            </h2>

            {/* Message display */}
            {message && (
              <div
                className={`mb-6 p-4 rounded-lg border ${
                  message.type === 'success'
                    ? 'bg-green-50 border-green-200 text-green-800'
                    : 'bg-red-50 border-red-200 text-red-800'
                }`}
              >
                <p className="text-sm font-medium">{message.text}</p>
              </div>
            )}

            {/* Login Form */}
            {!isResetMode ? (
              <form onSubmit={handleLogin} className="space-y-4">
                {/* Phone Number Input */}
                <div>
                  <label className="block text-sm font-medium text-foreground mb-2">
                    {t.phoneNumber}
                  </label>
                  <div className="relative">
                    <Phone size={18} className="absolute left-3 top-3 text-muted-foreground" />
                    <Input
                      type="tel"
                      placeholder="0912345678"
                      value={phoneNumber}
                      onChange={(e) => setPhoneNumber(e.target.value)}
                      disabled={isLoading}
                      className="pl-10"
                    />
                  </div>
                </div>

                {/* Password Input */}
                <div>
                  <label className="block text-sm font-medium text-foreground mb-2">
                    {t.password}
                  </label>
                  <div className="relative">
                    <Lock size={18} className="absolute left-3 top-3 text-muted-foreground" />
                    <Input
                      type="password"
                      placeholder="••••••••"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      disabled={isLoading}
                      className="pl-10"
                    />
                  </div>
                </div>

                {/* Submit Button */}
                <Button
                  type="submit"
                  disabled={isLoading}
                  className="w-full bg-primary hover:bg-primary/90 text-primary-foreground font-semibold h-10 mt-6"
                >
                  {isLoading ? t.sending : t.submit}
                </Button>

                {/* Forgot Password Link */}
                <button
                  type="button"
                  onClick={() => {
                    setIsResetMode(true);
                    setMessage(null);
                    setPhoneNumber('');
                    setPassword('');
                  }}
                  className="w-full text-center text-sm text-accent hover:text-accent/80 font-medium transition-colors"
                >
                  {t.forgotPassword}
                </button>
              </form>
            ) : (
              /* Password Reset Form */
              <form onSubmit={handlePasswordReset} className="space-y-4">
                <p className="text-sm text-muted-foreground mb-4">{t.passwordResetDesc}</p>

                {/* Phone Number Input */}
                <div>
                  <label className="block text-sm font-medium text-foreground mb-2">
                    {t.phoneNumber}
                  </label>
                  <div className="relative">
                    <Phone size={18} className="absolute left-3 top-3 text-muted-foreground" />
                    <Input
                      type="tel"
                      placeholder="0912345678"
                      value={phoneNumber}
                      onChange={(e) => setPhoneNumber(e.target.value)}
                      disabled={isLoading}
                      className="pl-10"
                    />
                  </div>
                </div>

                {/* Submit Button */}
                <Button
                  type="submit"
                  disabled={isLoading}
                  className="w-full bg-accent hover:bg-accent/90 text-accent-foreground font-semibold h-10 mt-6"
                >
                  {isLoading ? t.sending : t.sendReset}
                </Button>

                {/* Back to Login Link */}
                <button
                  type="button"
                  onClick={() => {
                    setIsResetMode(false);
                    setMessage(null);
                    setPhoneNumber('');
                    setPassword('');
                  }}
                  className="w-full text-center text-sm text-primary hover:text-primary/80 font-medium transition-colors"
                >
                  {t.back}
                </button>
              </form>
            )}

            {/* Terms of Service */}
            <div className="mt-8 pt-6 border-t border-border text-center">
              <p className="text-xs text-muted-foreground">
                {t.termsText}{' '}
                <a
                  href="/terms"
                  className="text-accent hover:text-accent/80 font-semibold transition-colors"
                >
                  {t.termsLink}
                </a>
              </p>
            </div>
          </div>
        </div>

        {/* Footer text */}
        <p className="text-center text-xs text-muted-foreground mt-6">
          © 2026 Yetemare. All rights reserved.
        </p>
      </div>
    </div>
  );
}
