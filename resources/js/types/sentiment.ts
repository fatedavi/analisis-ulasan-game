export type Stat = {
    mean: number;
    std: number;
};

export type ClassMetrics = {
    precision: Stat;
    recall: Stat;
    f1: Stat;
};

export type Summary = {
    run_id: string | null;
    total: number;
    positive: number;
    negative: number;
    sentiment_score: number;
    source: string;
    demo: boolean;
    generated_at: string | null;
};

export type Evaluation = {
    run_id: string | null;
    demo: boolean;
    best: { alpha: number; k: number };
    confusion_matrix: {
        tn: number;
        fp: number;
        fn: number;
        tp: number;
        orientation: string;
    };
    metrics_mean_over_folds: {
        accuracy: Stat;
        positive: ClassMetrics;
        negative: ClassMetrics;
        macro: ClassMetrics;
    };
    baseline_majority_accuracy: number;
    grid: { k: number; alpha: number; macro_f1: number; accuracy: number }[];
};

export type CloudWord = {
    token: string;
    count: number;
    label: 'positif' | 'negatif';
    share: number;
};

export type Words = {
    run_id: string;
    total_docs: number;
    total_tokens: number;
    words: CloudWord[];
};

export type ReviewRow = {
    review_id: string;
    username: string;
    rating: number;
    review: string;
    date: string | null;
    true_label: 'positif' | 'negatif';
    pred_label: 'positif' | 'negatif';
    proba_pos: number;
    fold: number;
};

export type ReviewsResponse = {
    run_id: string | null;
    total: number;
    positif: number;
    negatif: number;
    rating_distribution: Record<number, number>;
    page: number;
    per_page: number;
    last_page: number;
    label: 'positif' | 'negatif' | null;
    rating: number | null;
    q: string | null;
    data: ReviewRow[];
};
