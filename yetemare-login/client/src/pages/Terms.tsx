import { Button } from '@/components/ui/button';
import { useLocation } from 'wouter';
import { ChevronLeft } from 'lucide-react';

/**
 * Terms of Service Page
 * Professional Tech Design - Clean, readable layout with proper typography hierarchy
 */
export default function Terms() {
  const [, setLocation] = useLocation();

  return (
    <div className="min-h-screen bg-background">
      {/* Header with back button */}
      <div className="border-b border-border bg-card">
        <div className="max-w-3xl mx-auto px-4 py-6">
          <button
            onClick={() => setLocation('/')}
            className="flex items-center gap-2 text-primary hover:text-primary/80 transition-colors mb-4"
          >
            <ChevronLeft size={20} />
            <span className="text-sm font-medium">Back to Login</span>
          </button>
          <h1 className="text-3xl font-bold text-foreground">Terms of Service</h1>
          <p className="text-muted-foreground mt-2">Last updated: February 2026</p>
        </div>
      </div>

      {/* Main content */}
      <div className="max-w-3xl mx-auto px-4 py-12">
        <div className="prose prose-sm max-w-none text-foreground space-y-8">
          {/* Introduction */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">1. Acceptance of Terms</h2>
            <p className="text-muted-foreground leading-relaxed">
              By accessing and using Yetemare, you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.
            </p>
          </section>

          {/* Use License */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">2. Use License</h2>
            <p className="text-muted-foreground leading-relaxed mb-3">
              Permission is granted to temporarily download one copy of the materials (information or software) on Yetemare for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:
            </p>
            <ul className="list-disc list-inside space-y-2 text-muted-foreground">
              <li>Modifying or copying the materials</li>
              <li>Using the materials for any commercial purpose or for any public display</li>
              <li>Attempting to decompile or reverse engineer any software contained on Yetemare</li>
              <li>Removing any copyright or other proprietary notations from the materials</li>
              <li>Transferring the materials to another person or "mirroring" the materials on any other server</li>
            </ul>
          </section>

          {/* Disclaimer */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">3. Disclaimer</h2>
            <p className="text-muted-foreground leading-relaxed">
              The materials on Yetemare are provided on an 'as is' basis. Yetemare makes no warranties, expressed or implied, and hereby disclaims and negates all other warranties including, without limitation, implied warranties or conditions of merchantability, fitness for a particular purpose, or non-infringement of intellectual property or other violation of rights.
            </p>
          </section>

          {/* Limitations */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">4. Limitations</h2>
            <p className="text-muted-foreground leading-relaxed">
              In no event shall Yetemare or its suppliers be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use the materials on Yetemare, even if Yetemare or a Yetemare authorized representative has been notified orally or in writing of the possibility of such damage.
            </p>
          </section>

          {/* Accuracy of Materials */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">5. Accuracy of Materials</h2>
            <p className="text-muted-foreground leading-relaxed">
              The materials appearing on Yetemare could include technical, typographical, or photographic errors. Yetemare does not warrant that any of the materials on Yetemare are accurate, complete, or current. Yetemare may make changes to the materials contained on Yetemare at any time without notice.
            </p>
          </section>

          {/* Materials and Content */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">6. Materials and Content</h2>
            <p className="text-muted-foreground leading-relaxed">
              The materials on Yetemare are protected by copyright law and international treaties. You may not reproduce, distribute, transmit, display, perform, publish, license, create derivative works from, transfer, or sell any information, software, products or services obtained from Yetemare without prior written permission from Yetemare.
            </p>
          </section>

          {/* Links */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">7. Links</h2>
            <p className="text-muted-foreground leading-relaxed">
              Yetemare has not reviewed all of the sites linked to its Internet web site and is not responsible for the contents of any such linked site. The inclusion of any link does not imply endorsement by Yetemare of the site. Use of any such linked website is at the user's own risk.
            </p>
          </section>

          {/* Modifications */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">8. Modifications</h2>
            <p className="text-muted-foreground leading-relaxed">
              Yetemare may revise these terms of service for its website at any time without notice. By using this website, you are agreeing to be bound by the then current version of these terms of service.
            </p>
          </section>

          {/* Governing Law */}
          <section>
            <h2 className="text-xl font-semibold text-foreground mb-4">9. Governing Law</h2>
            <p className="text-muted-foreground leading-relaxed">
              These terms and conditions are governed by and construed in accordance with the laws of the jurisdiction in which Yetemare operates, and you irrevocably submit to the exclusive jurisdiction of the courts in that location.
            </p>
          </section>

          {/* Contact */}
          <section className="bg-muted p-6 rounded-lg border border-border">
            <h2 className="text-xl font-semibold text-foreground mb-4">10. Contact Information</h2>
            <p className="text-muted-foreground leading-relaxed">
              If you have any questions about these Terms of Service, please contact us at support@yetemare.com
            </p>
          </section>
        </div>

        {/* Back button at bottom */}
        <div className="mt-12 flex justify-center">
          <Button
            onClick={() => setLocation('/')}
            variant="outline"
            className="gap-2"
          >
            <ChevronLeft size={18} />
            Return to Login
          </Button>
        </div>
      </div>
    </div>
  );
}
