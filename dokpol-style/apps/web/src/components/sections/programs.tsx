import { Card, CardContent } from "@dokpol/ui";

const programs = [
  {
    icon: (
      <svg className="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
    ),
    title: "Pelayanan Medis",
    description: "Layanan kesehatan komprehensif dengan standar nasional dan internasional untuk seluruh anggota.",
    color: "bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400",
  },
  {
    icon: (
      <svg className="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
      </svg>
    ),
    title: "Laboratorium",
    description: "Fasilitas laboratorium modern dengan peralatan canggih untuk diagnosa akurat dan cepat.",
    color: "bg-accent-50 text-accent-600 dark:bg-accent-950 dark:text-accent-400",
  },
  {
    icon: (
      <svg className="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
    ),
    title: "Jaringan Faskes",
    description: "Jangkauan luas ke seluruh Indonesia dengan ratusan fasilitas kesehatan terintegrasi.",
    color: "bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400",
  },
];

export function Programs() {
  return (
    <section className="py-20 bg-background">
      <div className="container mx-auto">
        <div className="text-center max-w-3xl mx-auto mb-16">
          <h2 className="text-4xl font-bold text-foreground mb-4">
            Tiga Pilar Layanan Kami
          </h2>
          <p className="text-lg text-muted-foreground">
            Komitmen untuk memberikan layanan kesehatan terbaik dengan standar tertinggi
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {programs.map((program, index) => (
            <Card
              key={index}
              className="group hover:shadow-xl transition-all duration-300 border-2 hover:border-primary-300 dark:hover:border-primary-700"
            >
              <CardContent className="p-8 text-center">
                <div className={`inline-flex items-center justify-center w-20 h-20 rounded-full mb-6 ${program.color} group-hover:scale-110 transition-transform duration-300`}>
                  {program.icon}
                </div>
                <h3 className="text-2xl font-bold text-foreground mb-4">
                  {program.title}
                </h3>
                <p className="text-muted-foreground">
                  {program.description}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
}
